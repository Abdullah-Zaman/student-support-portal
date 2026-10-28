<?php

namespace App\Support\Registration;

/**
 * The domain lock: every student email must be a plain mailbox at
 * superior.edu.pk. Fixed in code on purpose (not read from config or the
 * database), so no setting or registration format can open registration
 * to another domain.
 *
 * The local part may hold only letters, digits and . _ % + - characters,
 * so tricks such as "user@evil.com?@superior.edu.pk" or
 * "x@superior.edu.pk.evil.com" never pass.
 */
final class StudentEmailDomain
{
    public const DOMAIN = '@superior.edu.pk';

    private const LOCAL_PART = '[a-z0-9._%+\-]{1,64}';

    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function isValid(string $email): bool
    {
        return preg_match('/\A'.self::LOCAL_PART.preg_quote(self::DOMAIN, '/').'\z/D', self::normalize($email)) === 1;
    }

    /**
     * The part before the domain of a valid student email, else null.
     */
    public static function localPart(string $email): ?string
    {
        return self::isValid($email) ? substr(self::normalize($email), 0, -strlen(self::DOMAIN)) : null;
    }
}
