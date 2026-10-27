<?php

namespace Database\Seeders;

use App\Actions\Correspondence\PostCorrespondence;
use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\ForwardTarget;
use App\Enums\RemarkType;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Three demo applications with correspondence between the Admin Officer,
 * the HOD and the Dean (forwards, remarks and replies), for trying out
 * the correspondence thread and the HOD/Dean portal. Demo messages are
 * never emailed.
 *
 * Runs at the end of DemoSeeder, or on its own with:
 *   php artisan db:seed --class=DemoForwardingSeeder
 *
 * It only uses demo students' in-progress applications, never the real
 * su92-bssem-f22-171 account, and does nothing once any correspondence
 * exists.
 */
class DemoForwardingSeeder extends Seeder
{
    private const PROTECTED_EMAILS = ['su92-bssem-f22-171@superior.edu.pk'];

    public function run(): void
    {
        if (CorrespondenceMessage::query()->exists()) {
            return;
        }

        $department = Department::query()->where('code', 'SE')->firstOrFail();
        $admin = User::query()->where('department_id', $department->id)->role(RoleName::AdminOfficer->value)->first();
        $hod = User::activeHodForDepartment($department->id);
        $dean = User::activeDeanForFaculty($department->faculty_id);

        if ($admin === null || $hod === null || $dean === null) {
            return;
        }

        $applications = Application::query()
            ->where('department_id', $department->id)
            ->whereIn('status', [ApplicationStatus::UnderReview, ApplicationStatus::InProgress, ApplicationStatus::Submitted])
            ->whereHas('student.user', fn ($query) => $query->whereNotIn('email', self::PROTECTED_EMAILS))
            ->oldest('id')
            ->take(3)
            ->get();

        $officer = CorrespondenceParty::AdminOfficer;
        $hodParty = CorrespondenceParty::Hod;
        $deanParty = CorrespondenceParty::Dean;

        $scenarios = [
            // With the HOD, who asks a question and then recommends it.
            [
                [CorrespondenceType::Forward, $admin, $officer, $hod, $hodParty, 'Student is asking for a fee instalment plan. Please review and advise whether the department supports it.'],
                [CorrespondenceType::Reply, $hod, $hodParty, $admin, $officer, 'Has the student cleared the previous semester dues?'],
                [CorrespondenceType::Reply, $admin, $officer, $hod, $hodParty, 'Yes, all previous dues are cleared. Only the current semester is pending.'],
                [CorrespondenceType::Remark, $hod, $hodParty, $admin, $officer, 'The student has a good record. I support the instalment plan.', RemarkType::Recommended],
            ],
            // HOD recommends and passes it to the Dean, who comments.
            [
                [CorrespondenceType::Forward, $admin, $officer, $hod, $hodParty, 'Request for a course withdrawal after the deadline because of a medical emergency. Medical certificate is attached.'],
                [CorrespondenceType::Remark, $hod, $hodParty, $admin, $officer, 'Medical reason is genuine. Recommended for approval by the Dean.', RemarkType::Recommended],
                [CorrespondenceType::Forward, $hod, $hodParty, $dean, $deanParty, 'Recommended at department level. Forwarding for your approval as it is past the faculty deadline.'],
                [CorrespondenceType::Remark, $dean, $deanParty, $admin, $officer, 'Approved in principle. The Admin Office should confirm the exact course code with the Examination department.', RemarkType::Comment],
            ],
            // Sent straight to the Dean, who does not recommend it.
            [
                [CorrespondenceType::Forward, $admin, $officer, $dean, $deanParty, 'Student requests a change of program from BSSE to BSAI in the 5th semester. Needs faculty-level decision.'],
                [CorrespondenceType::Remark, $dean, $deanParty, $admin, $officer, 'Program change is not possible after the 4th semester under faculty rules.', RemarkType::NotRecommended],
            ],
        ];

        foreach ($applications as $index => $application) {
            $this->seedScenario($application, $scenarios[$index]);
        }
    }

    /**
     * @param  list<array<int, mixed>>  $steps  [type, sender, from party, recipient, to party, body, remark type?]
     */
    private function seedScenario(Application $application, array $steps): void
    {
        $at = CarbonImmutable::parse($application->created_at)->addHours(6);

        foreach ($steps as $step) {
            $at = $at->addHours(5);
            [$type, $sender, $fromParty, $recipient, $toParty, $body] = $step;

            $message = CorrespondenceMessage::create([
                'application_id' => $application->id,
                'type' => $type,
                'author_id' => $sender->id,
                'from_party' => $fromParty,
                'from_user_id' => $sender->id,
                'to_party' => $toParty,
                'to_user_id' => $recipient->id,
                'remark_type' => $step[6] ?? null,
                'body' => $body,
                'email_status' => CorrespondenceEmailStatus::NotEmailed,
                'rfc_message_id' => PostCorrespondence::newRfcMessageId($application),
                'idempotency_key' => (string) Str::uuid(),
                'seen_at' => $at->addHour(),
                'seen_by_id' => $recipient->id,
            ]);
            $message->forceFill(['created_at' => $at])->save();

            if ($type !== CorrespondenceType::Forward) {
                continue;
            }

            $target = ForwardTarget::from($toParty->value);
            $application->update(['forwarded_to' => $target]);

            ApplicationEvent::create([
                'application_id' => $application->id,
                'user_id' => $sender->id,
                'event_type' => ApplicationEventType::Forwarded,
                'from_value' => $fromParty->value,
                'to_value' => $target->value,
                'visible_to_student' => true,
            ])->forceFill(['created_at' => $at])->save();
        }
    }
}
