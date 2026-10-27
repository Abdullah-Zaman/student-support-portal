<?php

namespace App\Support\Gmail;

/**
 * What Gmail returns for a sent email: its message id and the id of the
 * thread it joined in the sender's own mailbox.
 */
final readonly class GmailSentMessage
{
    public function __construct(
        public string $id,
        public ?string $threadId,
    ) {}
}
