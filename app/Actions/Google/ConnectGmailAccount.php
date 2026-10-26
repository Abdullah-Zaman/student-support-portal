<?php

namespace App\Actions\Google;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Two\User as GoogleUser;

/**
 * Stores (or replaces) a staff member's Gmail connection from Google's
 * OAuth callback. The Google account must be the staff member's portal
 * email, unless email mismatch is allowed (never in production).
 */
class ConnectGmailAccount
{
    /**
     * @throws ValidationException when the account cannot be connected
     */
    public function handle(User $user, GoogleUser $googleUser): GoogleAccount
    {
        $googleEmail = strtolower(trim((string) $googleUser->getEmail()));

        if ($googleEmail !== strtolower($user->email) && ! self::allowsEmailMismatch()) {
            $this->fail(__('Connect the Google account for your portal email (:email).', ['email' => $user->email]));
        }

        $alreadyUsed = GoogleAccount::query()
            ->where('google_email', $googleEmail)
            ->where('user_id', '!=', $user->id)
            ->exists();

        if ($alreadyUsed) {
            $this->fail(__('This Google account is already connected to another portal account.'));
        }

        $approvedScopes = $googleUser->approvedScopes;

        if (! in_array(GoogleAccount::GMAIL_SEND_SCOPE, $approvedScopes, true)) {
            $this->fail(__('Google did not grant permission to send email. Connect again and allow "Send email on your behalf".'));
        }

        if (! $googleUser->refreshToken) {
            $this->fail(__('Google did not return offline access. Remove the portal from your Google account permissions, then connect again.'));
        }

        $account = GoogleAccount::updateOrCreate(['user_id' => $user->id], [
            'google_email' => $googleEmail,
            'access_token' => $googleUser->token,
            'refresh_token' => $googleUser->refreshToken,
            'token_expires_at' => $googleUser->expiresIn ? now()->addSeconds((int) $googleUser->expiresIn) : null,
            'scopes' => implode(' ', $approvedScopes),
            'needs_reconnect' => false,
            'connected_at' => now(),
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($account)
            ->withProperties(['google_email' => $googleEmail])
            ->log('gmail connected');

        return $account;
    }

    /**
     * Only for local testing with personal Gmail accounts: never in
     * production, whatever GOOGLE_ALLOW_EMAIL_MISMATCH says.
     */
    public static function allowsEmailMismatch(): bool
    {
        return (bool) config('google.allow_email_mismatch') && ! app()->isProduction();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['gmail' => $message]);
    }
}
