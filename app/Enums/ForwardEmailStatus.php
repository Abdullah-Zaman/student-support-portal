<?php

namespace App\Enums;

/**
 * Email delivery of a forward. Forwards to an HOD or Dean are delivered
 * inside the portal; forwards to the Registrar wait for Phase D's Gmail
 * sending, which will move them to sent or failed.
 */
enum ForwardEmailStatus: string
{
    case NotRequired = 'not_required';
    case PendingEmail = 'pending_email';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'In portal',
            self::PendingEmail => 'Pending email',
            self::Sent => 'Email sent',
            self::Failed => 'Email failed',
        };
    }
}
