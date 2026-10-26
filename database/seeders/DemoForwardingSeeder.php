<?php

namespace Database\Seeders;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\ForwardEmailStatus;
use App\Enums\ForwardTarget;
use App\Enums\RemarkType;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\ApplicationForward;
use App\Models\ApplicationRemark;
use App\Models\Department;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Three demo applications already forwarded to the HOD and the Dean, with
 * their remarks, for trying out the HOD/Dean portal.
 *
 * Runs at the end of DemoSeeder, or on its own with:
 *   php artisan db:seed --class=DemoForwardingSeeder
 *
 * It only uses demo students' in-progress applications, never the real
 * su92-bssem-f22-171 account, and does nothing once any forward exists.
 */
class DemoForwardingSeeder extends Seeder
{
    private const PROTECTED_EMAILS = ['su92-bssem-f22-171@superior.edu.pk'];

    public function run(): void
    {
        if (ApplicationForward::query()->exists()) {
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

        $scenarios = [
            // With the HOD, who recommends it.
            [
                [$admin, RoleName::AdminOfficer, ForwardTarget::Hod, $hod, 'Student is asking for a fee instalment plan. Please review and advise whether the department supports it.'],
                [RemarkType::Recommended, 'The student has a good record. I support the instalment plan.'],
            ],
            // HOD recommends and passes it to the Dean, who comments.
            [
                [$admin, RoleName::AdminOfficer, ForwardTarget::Hod, $hod, 'Request for a course withdrawal after the deadline because of a medical emergency. Medical certificate is attached.'],
                [RemarkType::Recommended, 'Medical reason is genuine. Recommended for approval by the Dean.'],
                [$hod, RoleName::Hod, ForwardTarget::Dean, $dean, 'Recommended at department level. Forwarding for your approval as it is past the faculty deadline.'],
                [RemarkType::Comment, 'Approved in principle. The Admin Office should confirm the exact course code with the Examination department.'],
            ],
            // Sent straight to the Dean, who does not recommend it.
            [
                [$admin, RoleName::AdminOfficer, ForwardTarget::Dean, $dean, 'Student requests a change of program from BSSE to BSAI in the 5th semester. Needs faculty-level decision.'],
                [RemarkType::NotRecommended, 'Program change is not possible after the 4th semester under faculty rules.'],
            ],
        ];

        foreach ($applications as $index => $application) {
            $this->seedScenario($application, $scenarios[$index]);
        }
    }

    /**
     * @param  list<array<int, mixed>>  $steps  forwards [sender, role, target, recipient, note] each followed by a remark [type, body]
     */
    private function seedScenario(Application $application, array $steps): void
    {
        $at = CarbonImmutable::parse($application->created_at)->addHours(6);
        $forward = null;
        $recipient = null;

        foreach ($steps as $step) {
            $at = $at->addHours(5);

            if ($step[0] instanceof User) {
                [$sender, $fromRole, $target, $recipient, $note] = $step;

                $forward = ApplicationForward::create([
                    'application_id' => $application->id,
                    'from_user_id' => $sender->id,
                    'from_role' => $fromRole,
                    'to_role' => $target,
                    'to_user_id' => $recipient->id,
                    'note' => $note,
                    'email_status' => ForwardEmailStatus::NotRequired,
                ]);
                $forward->forceFill(['created_at' => $at])->save();

                $application->update(['forwarded_to' => $target]);

                ApplicationEvent::create([
                    'application_id' => $application->id,
                    'user_id' => $sender->id,
                    'event_type' => ApplicationEventType::Forwarded,
                    'from_value' => $fromRole->value,
                    'to_value' => $target->value,
                    'visible_to_student' => true,
                ])->forceFill(['created_at' => $at])->save();

                continue;
            }

            [$type, $body] = $step;

            ApplicationRemark::create([
                'application_id' => $application->id,
                'application_forward_id' => $forward?->id,
                'author_id' => $recipient?->id,
                'author_role' => $forward?->to_role->role(),
                'type' => $type,
                'body' => $body,
            ])->forceFill(['created_at' => $at])->save();
        }
    }
}
