<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\SupportRequest;
use App\Models\User;

/**
 * A support request belongs to its reporter (an Admin Officer, HOD or
 * Dean) and the Super Admin. Nobody else sees it, and students never do.
 */
class SupportRequestPolicy
{
    /**
     * Any staff member may open a list; which rows come back is scoped by
     * the query (own requests, or all for the Super Admin).
     */
    public function viewAny(User $user): bool
    {
        return $user->staffRole() !== null;
    }

    /**
     * The Super Admin's list of every request.
     */
    public function manage(User $user): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function create(User $user): bool
    {
        return in_array($user->staffRole(), [RoleName::AdminOfficer, RoleName::Hod, RoleName::Dean], true);
    }

    public function view(User $user, SupportRequest $request): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value)
            || ($user->staffRole() !== null && $request->reporter_id === $user->id);
    }

    /**
     * Set in progress, reply or resolve: Super Admin only, until resolved.
     */
    public function update(User $user, SupportRequest $request): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) && ! $request->isResolved();
    }
}
