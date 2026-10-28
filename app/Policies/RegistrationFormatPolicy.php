<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\RegistrationFormat;
use App\Models\User;

/**
 * Additional registration formats are managed by the Super Admin only.
 */
class RegistrationFormatPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $user, RegistrationFormat $format): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }
}
