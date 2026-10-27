<?php

namespace App\Policies;

use App\Enums\CorrespondenceParty;
use App\Enums\ForwardTarget;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\User;

class ApplicationPolicy
{
    /**
     * Listing is always allowed; which rows come back is enforced by
     * query scoping (ScopedToAdminDepartment, the student's own relation,
     * or Application::forwardedTo for an HOD/Dean), not by this policy.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::Student->value,
            RoleName::AdminOfficer->value,
            RoleName::SuperAdmin->value,
            RoleName::Hod->value,
            RoleName::Dean->value,
        ]);
    }

    /**
     * An HOD or Dean may only open applications forwarded to them.
     */
    public function view(User $user, Application $application): bool
    {
        if ($user->isReviewer()) {
            return $application->isForwardedTo($user);
        }

        return $this->ownsOrManages($user, $application);
    }

    /**
     * Allowed paths: Admin Officer → HOD or Dean; HOD →
     * Dean or Registrar; Dean → Registrar. An HOD or Dean may only pass
     * on an application forwarded to them, and nothing final is forwarded.
     */
    public function forward(User $user, Application $application, ForwardTarget $target): bool
    {
        $role = $user->staffRole();

        if ($role === null || $application->isFinal() || ! in_array($target, ForwardTarget::allowedFrom($role), true)) {
            return false;
        }

        return $role->isReviewer()
            ? $application->isForwardedTo($user)
            : $this->manages($user, $application);
    }

    /**
     * Recommended / Not recommended / Comment, by the HOD or Dean the
     * application was forwarded to.
     */
    public function remark(User $user, Application $application): bool
    {
        return $user->isReviewer()
            && ! $application->isFinal()
            && $application->isForwardedTo($user);
    }

    /**
     * The correspondence thread is for staff only, never students: the
     * department's Admin Officers, the HOD/Dean it was forwarded to, and
     * the Super Admin (read-only).
     */
    public function viewCorrespondence(User $user, Application $application): bool
    {
        if ($user->isReviewer()) {
            return $application->isForwardedTo($user);
        }

        return $this->manages($user, $application);
    }

    /**
     * Replies inside the portal: Admin Officer ↔ HOD, Admin Officer ↔
     * Dean and HOD ↔ Dean, only between parties already in this
     * application's thread, and never once it is closed or rejected.
     * The Super Admin only reads.
     */
    public function reply(User $user, Application $application, CorrespondenceParty $recipient): bool
    {
        $sender = CorrespondenceParty::forRole($user->staffRole());

        if ($sender === null || $application->isFinal() || ! $sender->canReplyTo($recipient)) {
            return false;
        }

        if (! $this->viewCorrespondence($user, $application)) {
            return false;
        }

        $parties = $application->correspondenceParties();

        return in_array($recipient, $parties, true)
            && ($sender === CorrespondenceParty::AdminOfficer || in_array($sender, $parties, true));
    }

    /**
     * The Registrar has no portal account. The Admin Officer records a
     * reply the Registrar sent by email, once something was forwarded to
     * the Registrar.
     */
    public function logExternalReply(User $user, Application $application): bool
    {
        return $user->hasRole(RoleName::AdminOfficer->value)
            && $this->manages($user, $application)
            && ! $application->isFinal()
            && $application->correspondence()->where('to_party', CorrespondenceParty::Registrar)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole(RoleName::Student->value) && $user->status === UserStatus::Active;
    }

    public function respondToRequest(User $user, Application $application): bool
    {
        return $user->hasRole(RoleName::Student->value)
            && $user->student?->id === $application->student_id
            && ! $this->isFinal($application);
    }

    /**
     * A free-text message, from the Admin Officer/Super Admin managing
     * this application, any time it isn't closed/rejected.
     */
    public function sendMessage(User $user, Application $application): bool
    {
        return $this->manages($user, $application) && ! $this->isFinal($application);
    }

    /**
     * Sending an info or document request — same rule as sendMessage;
     * kept as a separate ability name since it's a distinct action.
     */
    public function sendRequest(User $user, Application $application): bool
    {
        return $this->sendMessage($user, $application);
    }

    public function updateStatus(User $user, Application $application): bool
    {
        return $this->manages($user, $application);
    }

    public function updatePriority(User $user, Application $application): bool
    {
        return $this->manages($user, $application);
    }

    public function reject(User $user, Application $application): bool
    {
        return $this->manages($user, $application);
    }

    public function resolve(User $user, Application $application): bool
    {
        return $this->manages($user, $application);
    }

    public function close(User $user, Application $application): bool
    {
        return $this->manages($user, $application);
    }

    /**
     * Applications are never deleted.
     */
    public function delete(User $user, Application $application): bool
    {
        return false;
    }

    private function ownsOrManages(User $user, Application $application): bool
    {
        if ($user->hasRole(RoleName::Student->value)) {
            return $user->student?->id === $application->student_id;
        }

        return $this->manages($user, $application);
    }

    private function manages(User $user, Application $application): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return $user->hasRole(RoleName::AdminOfficer->value)
            && $user->department_id === $application->department_id;
    }

    private function isFinal(Application $application): bool
    {
        return $application->isFinal();
    }
}
