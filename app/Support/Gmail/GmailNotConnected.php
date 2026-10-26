<?php

namespace App\Support\Gmail;

use RuntimeException;

/**
 * The sender cannot send through Gmail until they (re)connect it: they
 * never connected, or Google refused their refresh token. Retrying
 * without reconnecting would fail the same way.
 */
class GmailNotConnected extends RuntimeException
{
    public static function notConnected(): self
    {
        return new self(__('Connect your Gmail first: Settings → Profile → Connected Gmail.'));
    }

    public static function reconnectRequired(): self
    {
        return new self(__('Gmail access was revoked or has expired. Reconnect your Gmail in Settings, then retry.'));
    }
}
