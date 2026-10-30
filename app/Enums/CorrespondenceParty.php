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
    case Office = 'office';

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
            ForwardTarget::AdminOfficer => self::AdminOfficer,
            ForwardTarget::Hod => self::Hod,
            ForwardTarget::Dean => self::Dean,
            ForwardTarget::Registrar => self::Registrar,
            ForwardTarget::Office => self::Office,
        };
    }

    /**
     * Fixed order of the parties, used to store each conversation's pair
     * the same way round.
     */
    public function order(): int
    {
        return match ($this) {
            self::AdminOfficer => 1,
            self::Hod => 2,
            self::Dean => 3,
            self::Registrar => 4,
            self::Office => 5,
        };
    }

    /**
     * Any two different parties may hold a conversation (Admin Officer,
     * HOD, Dean and Registrar), in any direction; nobody with themselves.
     */
    public function canConverseWith(self $other): bool
    {
        return $this !== $other;
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
     * Where this party opens an application in the portal; the Registrar
     * has none.
     */
    public function applicationRouteName(): ?string
    {
        return match ($this) {
            self::AdminOfficer => 'admin.applications.show',
            self::Hod => 'hod.applications.show',
            self::Dean => 'dean.applications.show',
            self::Registrar, self::Office => null,
        };
    }

    /**
     * Parties with no portal account, reached only by email.
     */
    public function isExternal(): bool
    {
        return $this === self::Registrar || $this === self::Office;
    }
}
