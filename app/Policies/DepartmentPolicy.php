<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $user, Department $department): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * Departments are never deleted — only deactivated, which closes new
     * registrations while existing students and applications keep working.
     */
    public function delete(User $user, Department $department): bool
    {
        return false;
    }
}
