<?php

namespace App\Support\Forwarding;

use App\Models\ApplicationForward;

/**
 * Sends a forward by email. Called for every forward right after it is
 * recorded, and again when the forwarder retries a pending or failed
 * email. Bound to GmailForwardMailer, which sends from the forwarder's
 * own Gmail and updates the forward's email_status.
 */
interface ForwardMailer
{
    public function send(ApplicationForward $forward): void;
}
