<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationAttachment;
use App\Models\Scopes\AdminDepartmentScope;
use App\Models\User;

class ApplicationAttachmentPolicy
{
    /**
     * Loaded without the admin-department scope: an attachment belonging to
     * another department must still resolve to a real Application here, so
     * the check below can deny it with a clean false instead of a null
     * being passed around.
     */
    public function view(User $user, ApplicationAttachment $attachment): bool
    {
        $application = Application::withoutGlobalScope(AdminDepartmentScope::class)
            ->find($attachment->application_id);

        return $application && $this->belongsToApplication($user, $application);
    }

    /**
     * Checked against the parent application, since the attachment
     * doesn't exist yet: authorize('create', [ApplicationAttachment::class, $application]).
     */
    public function create(User $user, Application $application): bool
    {
        // HOD and Dean only read attachments; they never upload.
        return ! $user->isReviewer() && $this->belongsToApplication($user, $application);
    }

    private function belongsToApplication(User $user, Application $application): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::AdminOfficer->value)) {
            return $user->department_id === $application->department_id;
        }

        if ($user->hasRole(RoleName::Student->value)) {
            return $user->student?->id === $application->student_id;
        }

        if ($user->isReviewer()) {
            return $application->isForwardedTo($user);
        }

        return false;
    }

    /**
     * Attachments are never deleted.
     */
    public function delete(User $user, ApplicationAttachment $attachment): bool
    {
        return false;
    }
}
