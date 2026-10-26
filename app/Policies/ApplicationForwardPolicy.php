<?php

namespace App\Policies;

use App\Enums\ForwardEmailStatus;
use App\Models\ApplicationForward;
use App\Models\User;

class ApplicationForwardPolicy
{
    /**
     * Only the forwarder may (re)send a forward's email — it goes out
     * from their own Gmail — and only while it is pending or failed.
     */
    public function sendEmail(User $user, ApplicationForward $forward): bool
    {
        return $forward->from_user_id === $user->id
            && in_array($forward->email_status, [ForwardEmailStatus::PendingEmail, ForwardEmailStatus::Failed], true);
    }
}
