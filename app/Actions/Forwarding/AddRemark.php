<?php

namespace App\Actions\Forwarding;

use App\Enums\RemarkType;
use App\Models\Application;
use App\Models\ApplicationRemark;
use App\Models\User;
use App\Notifications\RemarkAdded;
use Illuminate\Support\Facades\Notification;

/**
 * Records an HOD's or Dean's remark against the latest forward to their
 * role, and tells the department's Admin Officers. The caller authorizes
 * first. Remarks never reach the student.
 */
class AddRemark
{
    public function handle(Application $application, User $author, RemarkType $type, ?string $body): ApplicationRemark
    {
        $role = $author->staffRole();

        $forward = $application->forwards()
            ->where('to_role', $role?->value)
            ->latest('id')
            ->firstOrFail();

        $remark = ApplicationRemark::create([
            'application_id' => $application->id,
            'application_forward_id' => $forward->id,
            'author_id' => $author->id,
            'author_role' => $role,
            'type' => $type,
            'body' => $body !== null && trim($body) !== '' ? trim($body) : null,
        ]);

        Notification::send(User::adminOfficersInDepartment($application->department_id), new RemarkAdded($application, $remark));

        return $remark;
    }
}
