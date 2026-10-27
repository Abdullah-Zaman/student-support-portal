<?php

namespace App\Enums;

enum ForwardTarget: string
{
    case Hod = 'hod';
    case Dean = 'dean';
    case Registrar = 'registrar';

    /**
     * Where each role may forward an application. Admin Officer → HOD
     * or Dean; HOD → Dean or Registrar; Dean → Registrar. Any other role
     * (including the Super Admin, who only reads correspondence) may not
     * forward at all.
     *
     * @return list<self>
     */
    public static function allowedFrom(RoleName $role): array
    {
        return match ($role) {
            RoleName::AdminOfficer => [self::Hod, self::Dean],
            RoleName::Hod => [self::Dean, self::Registrar],
            RoleName::Dean => [self::Registrar],
            default => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Hod => 'HOD',
            self::Dean => 'Dean',
            self::Registrar => 'Registrar',
        };
    }

    /**
     * For the admin applications list, e.g. "With HOD".
     */
    public function badgeLabel(): string
    {
        return match ($this) {
            self::Hod => 'With HOD',
            self::Dean => 'With Dean',
            self::Registrar => 'Sent to Registrar',
        };
    }

    /**
     * The reviewer role that receives forwards to this target; the
     * Registrar has no portal role.
     */
    public function role(): ?RoleName
    {
        return match ($this) {
            self::Hod => RoleName::Hod,
            self::Dean => RoleName::Dean,
            self::Registrar => null,
        };
    }
}
