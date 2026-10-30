<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

/**
 * Only the department's Admin Officer and Super Admin manage its public
 * timetable link. HOD, Dean and students never do.
 */
class TimetableShareLinkPolicy
{
    public function manage(User $user, ?int $departmentId): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return $departmentId !== null;
        }

        return $user->hasRole(RoleName::AdminOfficer->value)
            && $departmentId !== null
            && $user->department_id === $departmentId;
    }
}
