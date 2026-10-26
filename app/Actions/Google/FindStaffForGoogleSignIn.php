<?php

namespace App\Actions\Google;

use App\Enums\UserStatus;
use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Matches a Google account to an existing, active staff user — by portal
 * email, or by the Google email they connected. Never creates users, and
 * students always use their password login.
 */
class FindStaffForGoogleSignIn
{
    /**
     * @throws ValidationException when the Google account may not sign in
     */
    public function handle(SocialiteUser $googleUser): User
    {
        $googleEmail = strtolower(trim((string) $googleUser->getEmail()));
        $raw = method_exists($googleUser, 'getRaw') ? $googleUser->getRaw() : [];

        if ($googleEmail === '' || (array_key_exists('email_verified', $raw) && ! $raw['email_verified'])) {
            $this->fail(__('Google did not confirm this email address.'));
        }

        $matchedByConnectedEmail = false;
        $user = User::query()->where('email', $googleEmail)->first();

        if ($user === null) {
            $user = GoogleAccount::query()->where('google_email', $googleEmail)->first()?->user;
            $matchedByConnectedEmail = $user !== null;
        }

        $staffDomains = config()->array('google.staff_domains');
        $domainAllowed = in_array(Str::after($googleEmail, '@'), $staffDomains, true)
            || ($matchedByConnectedEmail && ConnectGmailAccount::allowsEmailMismatch());

        if (! $domainAllowed) {
            $this->fail(__('Use your university Google account (:domains).', [
                'domains' => implode(', ', array_map(fn (string $domain) => '@'.$domain, $staffDomains)),
            ]));
        }

        if ($user === null) {
            $this->fail(__('Your account is not registered in the portal.'));
        }

        if ($user->staffRole() === null) {
            $this->fail(__('Google sign-in is for staff only. Students log in with their university email and password.'));
        }

        if ($user->status === UserStatus::Suspended) {
            $this->fail(__('Your account has been suspended. Contact the SE Department Admin Office.'));
        }

        return $user;
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['email' => $message]);
    }
}
