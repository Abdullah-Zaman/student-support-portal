<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class ActivityPolicy
{
    /**
     * The audit log is read-only and Super Admin only.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }
}
