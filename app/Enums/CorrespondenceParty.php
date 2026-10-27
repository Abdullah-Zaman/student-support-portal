<?php

namespace App\Enums;

/**
 * A side of an application's staff correspondence. The Registrar has no
 * portal account: forwards to them are emailed, and the Admin Officer
 * logs their emailed replies.
 */
enum CorrespondenceParty: string
{
    case AdminOfficer = 'admin_officer';
    case Hod = 'hod';
    case Dean = 'dean';
    case Registrar = 'registrar';

    /**
     * The party a staff member writes as. The Super Admin only reads
     * threads, so has none.
     */
    public static function forRole(?RoleName $role): ?self
    {
        return match ($role) {
            RoleName::AdminOfficer => self::AdminOfficer,
            RoleName::Hod => self::Hod,
            RoleName::Dean => self::Dean,
            default => null,
        };
    }

    public static function fromForwardTarget(ForwardTarget $target): self
    {
        return match ($target) {
            ForwardTarget::Hod => self::Hod,
            ForwardTarget::Dean => self::Dean,
            ForwardTarget::Registrar => self::Registrar,
        };
    }

    /**
     * Who may reply to whom inside the portal: Admin Officer ↔ HOD,
     * Admin Officer ↔ Dean and HOD ↔ Dean.
     */
    public function canReplyTo(self $recipient): bool
    {
        $pair = [$this, $recipient];

        return in_array($pair, [
            [self::AdminOfficer, self::Hod],
            [self::Hod, self::AdminOfficer],
            [self::AdminOfficer, self::Dean],
            [self::Dean, self::AdminOfficer],
            [self::Hod, self::Dean],
            [self::Dean, self::Hod],
        ], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::AdminOfficer => 'Admin Officer',
            self::Hod => 'HOD',
            self::Dean => 'Dean',
            self::Registrar => 'Registrar',
        };
    }

    /**
     * Where this party opens an application in the portal; the Registrar
     * has none.
     */
    public function applicationRouteName(): ?string
    {
        return match ($this) {
            self::AdminOfficer => 'admin.applications.show',
            self::Hod => 'hod.applications.show',
            self::Dean => 'dean.applications.show',
            self::Registrar => null,
        };
    }
}
