<?php

namespace App\Support;

use Illuminate\Mail\Message;
use WeakMap;

/**
 * The white logo for the header of system emails, embedded inline (CID)
 * so it shows without loading remote images. The notification template
 * is rendered twice per email (HTML and text), so the logo is embedded
 * once per message and its cid reused. Without a message (e.g. a mail
 * preview) it falls back to the public URL.
 */
class MailLogo
{
    public const PATH = 'images/superior-logo-white.png';

    /** @var WeakMap<Message, string>|null */
    private static ?WeakMap $embedded = null;

    public static function src(?Message $message): string
    {
        if ($message === null) {
            return asset(self::PATH);
        }

        self::$embedded ??= new WeakMap;

        return self::$embedded[$message] ??= $message->embed(public_path(self::PATH));
    }
}
