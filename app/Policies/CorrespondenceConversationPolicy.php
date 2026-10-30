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
     * Write in a portal conversation (not the Registrar's or an office's,
     * which are email-only) until the application is closed or rejected.
     */
    public function reply(User $user, CorrespondenceConversation $conversation): bool
    {
        return ! $conversation->isExternal()
            && ! $conversation->application->isFinal()
            && $this->view($user, $conversation);
    }

    /**
     * Record the Registrar's emailed reply: the Admin Officer, HOD or
     * Dean in that conversation, once something was forwarded to the
     * Registrar.
     */
    public function logExternalReply(User $user, CorrespondenceConversation $conversation): bool
    {
        return $conversation->isWithRegistrar()
            && ! $conversation->application->isFinal()
            && CorrespondenceParty::forRole($user->staffRole()) !== null
            && $this->view($user, $conversation)
            && $conversation->messages()->where('to_party', CorrespondenceParty::Registrar)->exists();
    }
}
