<?php

namespace App\Support\Forwarding;

use App\Models\ApplicationForward;

/**
 * Sends a forward by email. Called for every forward right after it is
 * recorded. Phase C binds LogForwardMailer (nothing is sent); Phase D
 * will send from the forwarder's own Gmail account and update the
 * forward's email_status.
 */
interface ForwardMailer
{
    public function send(ApplicationForward $forward): void;
}
