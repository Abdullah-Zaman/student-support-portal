<?php

namespace App\Enums;

enum ForwardTarget: string
{
    case AdminOfficer = 'admin_officer';
    case Hod = 'hod';
    case Dean = 'dean';
    case Registrar = 'registrar';
    case Office = 'office';

    /**
     * Where each role may forward an application: to any of the other
     * staff roles or the Registrar, in any order, never to themselves.
     * Any other role (including the Super Admin, who only reads
     * correspondence) may not forward at all.
     *
     * @return list<self>
     */
    public static function allowedFrom(RoleName $role): array
    {
        return match ($role) {
            RoleName::AdminOfficer => [self::Hod, self::Dean, self::Registrar, self::Office],
            RoleName::Hod => [self::AdminOfficer, self::Dean, self::Registrar, self::Office],
            RoleName::Dean => [self::AdminOfficer, self::Hod, self::Registrar, self::Office],
            default => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AdminOfficer => 'Admin Officer',
            self::Hod => 'HOD',
            self::Dean => 'Dean',
            self::Registrar => 'Registrar',
            self::Office => 'Office',
        };
    }

    /**
     * For the admin applications list, e.g. "With HOD".
     */
    public function badgeLabel(): string
    {
        return match ($this) {
            self::AdminOfficer => 'With Admin Officer',
            self::Hod => 'With HOD',
            self::Dean => 'With Dean',
            self::Registrar => 'Sent to Registrar',
            self::Office => 'Sent to office',
        };
    }

    /**
     * The staff role that receives forwards to this target; the
     * Registrar has no portal role.
     */
    public function role(): ?RoleName
    {
        return match ($this) {
            self::AdminOfficer => RoleName::AdminOfficer,
            self::Hod => RoleName::Hod,
            self::Dean => RoleName::Dean,
            self::Registrar, self::Office => null,
        };
    }
}
