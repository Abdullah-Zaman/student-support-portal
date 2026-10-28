<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::Student->value,
            RoleName::AdminOfficer->value,
            RoleName::SuperAdmin->value,
        ]);
    }

    public function view(User $user, Student $student): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::AdminOfficer->value)) {
            return $user->department_id === $student->user->department_id;
        }

        return $user->id === $student->user_id;
    }

    /**
     * The student may only update their own profile (current_semester,
     * enforced in the Form Request — not here).
     */
    public function update(User $user, Student $student): bool
    {
        return $user->id === $student->user_id;
    }

    /**
     * Super Admin for any department; Admin Officer for their own.
     */
    public function suspend(User $user, Student $student): bool
    {
        return $this->managedBy($user, $student);
    }

    public function reactivate(User $user, Student $student): bool
    {
        return $this->managedBy($user, $student);
    }

    /**
     * Correcting the student's name (e.g. a typo from registration) is an
     * Admin Officer/Super Admin action, distinct from update() above, which
     * is the student editing their own profile.
     */
    public function updateName(User $user, Student $student): bool
    {
        return $this->managedBy($user, $student);
    }

    /**
     * Marking a student's email verified by hand (their old mailbox may no
     * longer exist) is Super Admin only, and only while it is unverified.
     */
    public function markEmailVerified(User $user, Student $student): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) && $student->user->email_verified_at === null;
    }

    /**
     * Students are never deleted.
     */
    public function delete(User $user, Student $student): bool
    {
        return false;
    }

    /**
     * Editing program/current_semester/batch for any department's student
     * is Super Admin only — deliberately not extended to Admin Officer.
     */
    public function updateProfile(User $user, Student $student): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    private function managedBy(User $user, Student $student): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return $user->hasRole(RoleName::AdminOfficer->value)
            && $user->department_id === $student->user->department_id;
    }
}
