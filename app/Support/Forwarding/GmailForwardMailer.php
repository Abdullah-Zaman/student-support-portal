<?php

namespace App\Support\Forwarding;

use App\Jobs\SendForwardEmail;
use App\Models\ApplicationForward;

/**
 * Emails every forward from the forwarder's own connected Gmail, on the
 * queue. The job moves the forward's email_status to sent or failed.
 */
class GmailForwardMailer implements ForwardMailer
{
    public function send(ApplicationForward $forward): void
    {
        SendForwardEmail::dispatch($forward);
    }
}
