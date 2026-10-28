<?php

namespace App\Enums;

enum SupportRequestStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
        };
    }

    /**
     * Flux badge colour; the label (and icon) always accompany it.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'amber',
            self::InProgress => 'purple',
            self::Resolved => 'green',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Open => 'clock',
            self::InProgress => 'arrow-path',
            self::Resolved => 'check-circle',
        };
    }
}
