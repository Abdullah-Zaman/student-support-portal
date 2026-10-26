<?php

namespace App\Enums;

enum RoleName: string
{
    case SuperAdmin = 'super_admin';
    case AdminOfficer = 'admin_officer';
    case Student = 'student';
    case Hod = 'hod';
    case Dean = 'dean';

    /**
     * HOD and Dean review applications forwarded to them; they share one
     * read-only portal.
     */
    public function isReviewer(): bool
    {
        return $this === self::Hod || $this === self::Dean;
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::AdminOfficer => 'Admin Officer',
            self::Student => 'Student',
            self::Hod => 'HOD',
            self::Dean => 'Dean',
        };
    }
}
