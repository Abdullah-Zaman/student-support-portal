<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Office;
use App\Models\User;

/**
 * Only the Admin Officer manages offices and their contacts, and only
 * in their own department. Contacts are managed through update().
 * Offices are never deleted, only deactivated.
 */
class OfficePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::AdminOfficer->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::AdminOfficer->value) && $user->department_id !== null;
    }

    public function update(User $user, Office $office): bool
    {
        return $user->hasRole(RoleName::AdminOfficer->value)
            && $user->department_id !== null
            && $user->department_id === $office->department_id;
    }

    public function delete(User $user, Office $office): bool
    {
        return false;
    }
}
