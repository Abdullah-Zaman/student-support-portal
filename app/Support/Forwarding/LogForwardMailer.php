<?php

namespace App\Support\Forwarding;

use App\Models\ApplicationForward;
use Illuminate\Support\Facades\Log;

/**
 * Sends nothing: it only logs that a forward was recorded, leaving its
 * email_status as it is (Registrar forwards stay "pending email").
 */
class LogForwardMailer implements ForwardMailer
{
    public function send(ApplicationForward $forward): void
    {
        Log::info('Application forward recorded; email sending is not enabled yet.', [
            'forward_id' => $forward->id,
            'application_id' => $forward->application_id,
            'to_role' => $forward->to_role->value,
            'email_status' => $forward->email_status->value,
        ]);
    }
}
