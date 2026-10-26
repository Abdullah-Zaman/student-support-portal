<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * Programs are never deleted — only deactivated, so students who
     * already belong to one keep working.
     */
    public function delete(User $user, Program $program): bool
    {
        return false;
    }
}
