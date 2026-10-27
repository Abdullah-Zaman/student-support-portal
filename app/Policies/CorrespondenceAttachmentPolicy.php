<?php

namespace App\Policies;

use App\Models\Application;
use App\Models\CorrespondenceAttachment;
use App\Models\Scopes\AdminDepartmentScope;
use App\Models\User;

class CorrespondenceAttachmentPolicy
{
    /**
     * Whoever may view the application's correspondence; never a
     * student. Loaded without the admin-department scope so another
     * department's file is denied cleanly rather than resolving to null.
     */
    public function view(User $user, CorrespondenceAttachment $attachment): bool
    {
        $application = Application::withoutGlobalScope(AdminDepartmentScope::class)
            ->find($attachment->application_id);

        return $application !== null && $user->can('viewCorrespondence', $application);
    }
}
