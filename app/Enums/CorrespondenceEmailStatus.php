<?php

namespace App\Enums;

/**
 * Email delivery of a correspondence message, sent from the sender's own
 * Gmail. A message from someone without a usable Gmail connection waits
 * as AwaitingGmail and goes out once they connect. NotEmailed covers
 * logged Registrar replies and copied rows that were never emailed.
 */
enum CorrespondenceEmailStatus: string
{
    case NotEmailed = 'not_emailed';
    case AwaitingGmail = 'awaiting_gmail';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotEmailed => 'Not emailed',
            self::AwaitingGmail => 'Pending: connect Gmail to send',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::NotEmailed => 'zinc',
            self::AwaitingGmail, self::Sending => 'amber',
            self::Sent => 'green',
            self::Failed => 'red',
        };
    }
}
