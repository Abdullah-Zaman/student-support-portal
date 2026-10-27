<?php

namespace App\Enums;

enum CorrespondenceType: string
{
    case Forward = 'forward';
    case Reply = 'reply';
    case Remark = 'remark';
    case LoggedReply = 'logged_reply';

    public function label(): string
    {
        return match ($this) {
            self::Forward => 'Forward',
            self::Reply => 'Reply',
            self::Remark => 'Remark',
            self::LoggedReply => 'Logged reply',
        };
    }
}
