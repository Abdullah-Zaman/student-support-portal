<?php

namespace App\Policies;

use App\Enums\CorrespondenceEmailStatus;
use App\Models\CorrespondenceMessage;
use App\Models\User;

class CorrespondenceMessagePolicy
{
    /**
     * Only the sender may (re)send a message's email, since it goes out
     * from their own Gmail: after it failed, or while it waits for a
     * Gmail connection they now have.
     */
    public function retryEmail(User $user, CorrespondenceMessage $message): bool
    {
        if ($message->from_user_id !== $user->id || $message->author_id !== $user->id) {
            return false;
        }

        return $message->email_status === CorrespondenceEmailStatus::Failed
            || ($message->email_status === CorrespondenceEmailStatus::AwaitingGmail && $user->hasConnectedGmail());
    }
}
