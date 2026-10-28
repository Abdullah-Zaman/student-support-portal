<?php

namespace App\Policies;

use App\Models\CorrespondenceAttachment;
use App\Models\User;

class CorrespondenceAttachmentPolicy
{
    /**
     * Only the two participants of the conversation the file was sent
     * in; never a student, the Super Admin or another staff member.
     */
    public function view(User $user, CorrespondenceAttachment $attachment): bool
    {
        $conversation = $attachment->message->conversation;

        return $conversation !== null && $user->can('view', $conversation);
    }
}
