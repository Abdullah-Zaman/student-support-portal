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
    public const SEND_URL = 'https://gmail.googleapis.com/upload/gmail/v1/users/me/messages/send?uploadType=multipart';

    public const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    /**
     * Pass the thread id Gmail returned for the sender's previous email
     * of the same conversation to keep it in one thread in their mailbox
     * (Gmail also needs the same subject for that).
     *
     * @throws GmailNotConnected when the sender must (re)connect Gmail
     * @throws RequestException when Gmail rejects the message
     * @throws ConnectionException when Google cannot be reached
     */
    public function send(User $sender, Email $email, ?string $threadId = null): GmailSentMessage
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

        $response = $this->post($this->accessToken($account), $email, $threadId);

        if ($response->status() === 401) {
            $response = $this->post($this->accessToken($account, forceRefresh: true), $email, $threadId);
        }

        $response->throw();

        $sentThreadId = $response->json('threadId');

        return new GmailSentMessage((string) $response->json('id'), is_string($sentThreadId) ? $sentThreadId : null);
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

    /**
     * A multipart upload: JSON metadata (the thread id) and the raw MIME
     * message, which may be larger than a JSON "raw" field allows.
     */
    private function post(string $accessToken, Email $email, ?string $threadId): Response
    {
        $boundary = 'portal-'.bin2hex(random_bytes(12));
        $metadata = $threadId !== null ? ['threadId' => $threadId] : (object) [];

        $body = "--{$boundary}\r\n"
            ."Content-Type: application/json; charset=UTF-8\r\n\r\n"
            .json_encode($metadata)."\r\n"
            ."--{$boundary}\r\n"
            ."Content-Type: message/rfc822\r\n\r\n"
            .$email->toString()."\r\n"
            ."--{$boundary}--";

        return Http::withToken($accessToken)
            ->withBody($body, 'multipart/related; boundary='.$boundary)
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
