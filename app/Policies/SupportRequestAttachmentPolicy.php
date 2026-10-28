<?php

namespace App\Policies;

use App\Models\SupportRequestAttachment;
use App\Models\User;

class SupportRequestAttachmentPolicy
{
    /**
     * Whoever may view the request: its reporter and the Super Admin.
     */
    public function view(User $user, SupportRequestAttachment $attachment): bool
    {
        return $user->can('view', $attachment->supportRequest);
    }
}
