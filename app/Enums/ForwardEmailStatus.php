<?php

namespace App\Enums;

/**
 * Email delivery of a forward. Every forward is emailed from the
 * forwarder's own Gmail: it starts as pending and becomes sent or
 * failed. NotRequired remains on HOD/Dean forwards recorded before
 * forwards were emailed.
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
