<?php

namespace App\Enums;

enum RemarkType: string
{
    case Recommended = 'recommended';
    case NotRecommended = 'not_recommended';
    case Comment = 'comment';

    public function label(): string
    {
        return match ($this) {
            self::Recommended => 'Recommended',
            self::NotRecommended => 'Not recommended',
            self::Comment => 'Comment',
        };
    }
}
