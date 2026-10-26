<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Faculty;
use App\Models\User;

class FacultyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $user, Faculty $faculty): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * Faculties are never deleted — only deactivated, which closes new
     * registrations while existing students and applications keep working.
     */
    public function delete(User $user, Faculty $faculty): bool
    {
        return false;
    }
}
