<?php

namespace App\Actions\Forwarding;

use App\Actions\Correspondence\PostCorrespondence;
use App\Enums\ApplicationEventType;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\ForwardTarget;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\CorrespondenceMessage;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ApplicationForwardedForReview;
use App\Notifications\ApplicationForwardedOnward;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Records one forward of an application as a message in the private
 * conversation between the sender and the recipient (who may forward
 * where is the ApplicationPolicy's job; the caller authorizes first).
 * The status is left unchanged. The recipient is notified, the student
 * gets a short notice with no note (not when it goes back to the Admin
 * Officer), and the Admin Officers get a neutral notice when others pass
 * it on. The email goes from the sender's own Gmail, or waits until they
 * connect it.
 */
class ForwardApplication
{
    public const REGISTRAR_EMAIL_SETTING = 'forwarding.registrar_email';

    public function __construct(private PostCorrespondence $post) {}

    /**
     * Why a forward to this target is not possible right now (no active
     * HOD or Dean, or no Registrar email), or null when it is.
     */
    public function unavailableReason(Application $application, ForwardTarget $target): ?string
    {
        $isAvailable = match ($target) {
            ForwardTarget::AdminOfficer => User::adminOfficersInDepartment($application->department_id)->isNotEmpty(),
            ForwardTarget::Hod => User::activeHodForDepartment($application->department_id) !== null,
            ForwardTarget::Dean => User::activeDeanForFaculty($application->department?->faculty_id) !== null,
            ForwardTarget::Registrar => self::registrarEmail() !== null,
        };

        if ($isAvailable) {
            return null;
        }

        return match ($target) {
            ForwardTarget::AdminOfficer => __('No Admin Officer for this department.'),
            ForwardTarget::Hod => __('No active HOD for this department.'),
            ForwardTarget::Dean => __('No active Dean for this faculty.'),
            ForwardTarget::Registrar => __('The Registrar email is not set. Ask the Super Admin to add it in Settings.'),
        };
    }

    /**
     * @throws ValidationException when the target has no recipient
     */
    public function handle(Application $application, User $sender, ForwardTarget $target, string $note, string $idempotencyKey): CorrespondenceMessage
    {
        $reason = $this->unavailableReason($application, $target);

        if ($reason !== null) {
            throw ValidationException::withMessages(['forward_target' => $reason]);
        }

        $fromParty = CorrespondenceParty::forRole($sender->staffRole());

        if ($fromParty === null) {
            throw ValidationException::withMessages(['forward_target' => __('Only the Admin Officer, HOD or Dean can forward.')]);
        }

        $toParty = CorrespondenceParty::fromForwardTarget($target);

        if (! $fromParty->canConverseWith($toParty)) {
            throw ValidationException::withMessages(['forward_target' => __('You cannot forward an application to yourself.')]);
        }

        $recipient = $this->post->recipientFor($application, $toParty, $fromParty);

        $forward = $this->post->handle($application, $sender, [
            'type' => CorrespondenceType::Forward,
            'from_party' => $fromParty,
            'from_user_id' => $sender->id,
            'to_party' => $toParty,
            'to_user_id' => $recipient?->id,
            'to_email' => $target === ForwardTarget::Registrar ? self::registrarEmail() : null,
            'body' => $note,
        ], $idempotencyKey, alsoInTransaction: function (CorrespondenceMessage $forward) use ($application, $sender, $fromParty, $target) {
            $application->update(['forwarded_to' => $target === ForwardTarget::AdminOfficer ? null : $target]);

            ApplicationEvent::create([
                'application_id' => $application->id,
                'user_id' => $sender->id,
                'event_type' => ApplicationEventType::Forwarded,
                'from_value' => $fromParty->value,
                'to_value' => $target->value,
                'visible_to_student' => $target !== ForwardTarget::AdminOfficer,
            ]);
        });

        if (! $forward->wasRecentlyCreated) {
            return $forward;
        }

        if ($target !== ForwardTarget::AdminOfficer) {
            $application->student->user->notify(new ApplicationForwardedForReview($application, $target));
        }

        if ($fromParty !== CorrespondenceParty::AdminOfficer && $target !== ForwardTarget::AdminOfficer) {
            Notification::send(User::adminOfficersInDepartment($application->department_id), new ApplicationForwardedOnward($application, $forward));
        }

        return $forward;
    }

    public static function registrarEmail(): ?string
    {
        $email = trim((string) Setting::get(self::REGISTRAR_EMAIL_SETTING));

        return $email !== '' ? $email : null;
    }
}
