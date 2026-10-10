<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;

class SettingPolicy
{
    public function manage(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }
}
