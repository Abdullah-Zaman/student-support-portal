<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ApplicationCategory;
use App\Models\User;

class ApplicationCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function update(User $user, ApplicationCategory $category): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * Categories are never deleted — only deactivated, so applications
     * that already reference one keep working.
     */
    public function delete(User $user, ApplicationCategory $category): bool
    {
        return false;
    }
}
