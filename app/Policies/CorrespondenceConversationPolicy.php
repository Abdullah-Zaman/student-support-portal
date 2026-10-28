<?php

namespace App\Policies;

use App\Enums\CorrespondenceParty;
use App\Models\CorrespondenceConversation;
use App\Models\User;

/**
 * Staff conversations are private to their two participants. The Super
 * Admin, students and every other staff member are denied.
 */
class CorrespondenceConversationPolicy
{
    public function view(User $user, CorrespondenceConversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    /**
     * Write in a portal conversation (not the Registrar's, which is
     * email-only) until the application is closed or rejected.
     */
    public function reply(User $user, CorrespondenceConversation $conversation): bool
    {
        return ! $conversation->isWithRegistrar()
            && ! $conversation->application->isFinal()
            && $this->view($user, $conversation);
    }

    /**
     * Record the Registrar's emailed reply: the HOD or Dean in that
     * conversation, once something was forwarded to the Registrar.
     */
    public function logExternalReply(User $user, CorrespondenceConversation $conversation): bool
    {
        return $conversation->isWithRegistrar()
            && ! $conversation->application->isFinal()
            && $user->isReviewer()
            && $this->view($user, $conversation)
            && $conversation->messages()->where('to_party', CorrespondenceParty::Registrar)->exists();
    }
}
