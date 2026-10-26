<?php

namespace App\Support\Gmail;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Sends a MIME email from a staff member's own connected Gmail through
 * the Gmail API, refreshing the access token when it has expired. If
 * Google refuses the refresh token, the account is marked as needing a
 * reconnect.
 */
class GmailSender
{
    public const SEND_URL = 'https://gmail.googleapis.com/upload/gmail/v1/users/me/messages/send?uploadType=media';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /**
     * Returns the Gmail message id.
     *
     * @throws GmailNotConnected when the sender must (re)connect Gmail
     * @throws RequestException when Gmail rejects the message
     * @throws ConnectionException when Google cannot be reached
     */
    public function send(User $sender, Email $email): string
    {
        $account = $sender->googleAccount;

        if ($account === null) {
            throw GmailNotConnected::notConnected();
        }

        if ($account->needs_reconnect) {
            throw GmailNotConnected::reconnectRequired();
        }

        $email->from(new Address($account->google_email, $sender->name));
        $this->applyAlwaysTo($email);

        $response = $this->post($this->accessToken($account), $email);

        if ($response->status() === 401) {
            $response = $this->post($this->accessToken($account, forceRefresh: true), $email);
        }

        return (string) $response->throw()->json('id');
    }

    /**
     * Revoke the tokens at Google. A failure is ignored: the tokens are
     * deleted from the portal either way.
     */
    public function revoke(GoogleAccount $account): void
    {
        rescue(fn () => Http::asForm()->post(self::REVOKE_URL, [
            'token' => $account->refresh_token,
        ]), report: false);
    }

    /**
     * @throws GmailNotConnected when Google refuses the refresh token
     */
    private function accessToken(GoogleAccount $account, bool $forceRefresh = false): string
    {
        if (! $forceRefresh && $account->hasFreshAccessToken()) {
            return (string) $account->access_token;
        }

        $response = Http::asForm()->post(self::TOKEN_URL, [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $account->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if (in_array($response->status(), [400, 401], true)) {
            $account->update(['needs_reconnect' => true]);

            throw GmailNotConnected::reconnectRequired();
        }

        $response->throw();

        $account->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return (string) $account->access_token;
    }

    private function post(string $accessToken, Email $email): Response
    {
        return Http::withToken($accessToken)
            ->withBody($email->toString(), 'message/rfc822')
            ->post(self::SEND_URL);
    }

    /**
     * Outside production, MAIL_ALWAYS_TO (if set) catches Gmail emails
     * too, just like the SMTP mailer.
     */
    private function applyAlwaysTo(Email $email): void
    {
        $alwaysTo = config('mail.always_to');

        if (app()->isProduction() || ! is_string($alwaysTo) || $alwaysTo === '') {
            return;
        }

        $email->to($alwaysTo);
        $email->getHeaders()->remove('Cc');
        $email->getHeaders()->remove('Bcc');
    }
}
