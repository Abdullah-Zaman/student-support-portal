<?php

namespace App\Support;

/**
 * The one place that decides whether the test-user tool exists: the
 * ALLOW_TEST_USER_CREATION flag is on and the app is not in production.
 */
final class TestUserCreation
{
    public static function enabled(): bool
    {
        return config('portal.allow_test_user_creation') === true && ! app()->isProduction();
    }
}
