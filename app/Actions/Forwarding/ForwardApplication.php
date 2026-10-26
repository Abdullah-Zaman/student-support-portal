<?php

namespace App\Actions\Forwarding;

use App\Enums\ApplicationEventType;
use App\Enums\ForwardEmailStatus;
use App\Enums\ForwardTarget;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\ApplicationForward;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ApplicationForwardedForReview;
use App\Notifications\ApplicationForwardedOnward;
use App\Notifications\ApplicationForwardedToReviewer;
use App\Support\Forwarding\ForwardMailer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Records one forward of an application (who may forward where is the
 * ApplicationPolicy's job; the caller authorizes first). The status is
 * left unchanged. The HOD/Dean gets a notification, the student a short
 * one with no note, and the Admin Officers one when an HOD or Dean
 * passes it on. Every forward is handed to the ForwardMailer, which
 * emails it from the sender's own Gmail, so a sender without a usable
 * Gmail connection cannot forward.
 */
class ForwardApplication
{
    public const REGISTRAR_EMAIL_SETTING = 'forwarding.registrar_email';

    public function __construct(private ForwardMailer $mailer) {}

    /**
     * Why a forward to this target is not possible right now (no active
     * HOD or Dean, or no Registrar email), or null when it is.
     */
    public function unavailableReason(Application $application, ForwardTarget $target): ?string
    {
        $isAvailable = match ($target) {
            ForwardTarget::Hod => User::activeHodForDepartment($application->department_id) !== null,
            ForwardTarget::Dean => User::activeDeanForFaculty($application->department?->faculty_id) !== null,
            ForwardTarget::Registrar => self::registrarEmail() !== null,
        };

        if ($isAvailable) {
            return null;
        }

        return match ($target) {
            ForwardTarget::Hod => __('No active HOD for this department.'),
            ForwardTarget::Dean => __('No active Dean for this faculty.'),
            ForwardTarget::Registrar => __('The Registrar email is not set. Ask the Super Admin to add it in Settings.'),
        };
    }

    /**
     * @throws ValidationException when the sender has no usable Gmail or the target has no recipient
     */
    public function handle(Application $application, User $sender, ForwardTarget $target, string $note): ApplicationForward
    {
        if (! $sender->hasConnectedGmail()) {
            throw ValidationException::withMessages(['forward_target' => self::gmailRequiredMessage($sender)]);
        }

        $reason = $this->unavailableReason($application, $target);

        if ($reason !== null) {
            throw ValidationException::withMessages(['forward_target' => $reason]);
        }

        $fromRole = $sender->staffRole();
        $recipient = match ($target) {
            ForwardTarget::Hod => User::activeHodForDepartment($application->department_id),
            ForwardTarget::Dean => User::activeDeanForFaculty($application->department?->faculty_id),
            ForwardTarget::Registrar => null,
        };

        $forward = DB::transaction(function () use ($application, $sender, $fromRole, $target, $recipient, $note) {
            $forward = ApplicationForward::create([
                'application_id' => $application->id,
                'from_user_id' => $sender->id,
                'from_role' => $fromRole,
                'to_role' => $target,
                'to_user_id' => $recipient?->id,
                'to_email' => $target === ForwardTarget::Registrar ? self::registrarEmail() : null,
                'note' => $note,
                'email_status' => ForwardEmailStatus::PendingEmail,
            ]);

            $application->update(['forwarded_to' => $target]);

            ApplicationEvent::create([
                'application_id' => $application->id,
                'user_id' => $sender->id,
                'event_type' => ApplicationEventType::Forwarded,
                'from_value' => $fromRole?->value,
                'to_value' => $target->value,
                'visible_to_student' => true,
            ]);

            return $forward;
        });

        if ($recipient !== null) {
            $recipient->notify(new ApplicationForwardedToReviewer($application, $forward));
        }

        $application->student->user->notify(new ApplicationForwardedForReview($application, $target));

        if ($fromRole?->isReviewer()) {
            Notification::send(User::adminOfficersInDepartment($application->department_id), new ApplicationForwardedOnward($application, $forward));
        }

        $this->mailer->send($forward);

        return $forward;
    }

    /**
     * Send a pending or failed forward email again (the caller authorizes
     * first). Old "pending email" Registrar forwards go out this way once
     * the forwarder has connected Gmail.
     *
     * @throws ValidationException when the forwarder has no usable Gmail
     */
    public function resendEmail(ApplicationForward $forward): void
    {
        if (! $forward->sender->hasConnectedGmail()) {
            throw ValidationException::withMessages(['forward_email' => self::gmailRequiredMessage($forward->sender)]);
        }

        $forward->update(['email_status' => ForwardEmailStatus::PendingEmail, 'email_error' => null]);

        $this->mailer->send($forward);
    }

    public static function gmailRequiredMessage(User $sender): string
    {
        return $sender->googleAccount?->needs_reconnect
            ? __('Reconnect your Gmail first: Settings → Profile → Connected Gmail. Forwards are emailed from your own Gmail.')
            : __('Connect your Gmail first: Settings → Profile → Connected Gmail. Forwards are emailed from your own Gmail.');
    }

    public static function registrarEmail(): ?string
    {
        $email = trim((string) Setting::get(self::REGISTRAR_EMAIL_SETTING));

        return $email !== '' ? $email : null;
    }
}
