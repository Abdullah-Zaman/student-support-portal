<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * A Super Admin may not suspend themselves, and the last remaining
     * active Super Admin may never be suspended.
     */
    public function suspend(User $user, User $target): bool
    {
        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            return false;
        }

        if ($user->id === $target->id) {
            return false;
        }

        if ($target->hasRole(RoleName::SuperAdmin->value) && $this->isLastActiveSuperAdmin($target)) {
            return false;
        }

        return true;
    }

    public function reactivate(User $user, User $target): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    private function isLastActiveSuperAdmin(User $target): bool
    {
        return User::query()
            ->role(RoleName::SuperAdmin->value)
            ->where('status', UserStatus::Active)
            ->where('id', '!=', $target->id)
            ->doesntExist();
    }
}
