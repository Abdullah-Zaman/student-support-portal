<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\TimetableVersion;
use App\Models\User;

/**
 * Timetable versions are managed only by their department's Admin
 * Officer and by Super Admin. Students never touch a version directly:
 * they only see the classes of their department's published version.
 */
class TimetableVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([RoleName::AdminOfficer->value, RoleName::SuperAdmin->value]);
    }

    public function view(User $user, TimetableVersion $version): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return $user->hasRole(RoleName::AdminOfficer->value)
            && $user->department_id === $version->department_id;
    }

    /**
     * The read-only search and statistics over a department's published
     * timetable: that department's Admin Officer, or Super Admin.
     */
    public function explore(User $user, ?int $departmentId): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return $user->hasRole(RoleName::AdminOfficer->value)
            && $departmentId !== null
            && $user->department_id === $departmentId;
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Publishing a draft and restoring an archived version are the same
     * action: it becomes the one version students see.
     */
    public function publish(User $user, TimetableVersion $version): bool
    {
        return $this->view($user, $version);
    }

    /**
     * Download the original uploaded workbook.
     */
    public function download(User $user, TimetableVersion $version): bool
    {
        return $this->view($user, $version);
    }

    /**
     * No delete: versions are kept so an older one can be restored.
     */
    public function delete(User $user, TimetableVersion $version): bool
    {
        return false;
    }
}
