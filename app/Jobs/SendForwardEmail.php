<?php

namespace App\Jobs;

use App\Enums\ForwardEmailStatus;
use App\Models\ApplicationForward;
use App\Support\Forwarding\ForwardEmailBuilder;
use App\Support\Gmail\GmailNotConnected;
use App\Support\Gmail\GmailSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails one forward from the forwarder's own Gmail and records the
 * outcome on the forward. Temporary Gmail trouble (network, rate limit,
 * server error) is retried; anything else fails at once with a clear
 * reason, and the forwarder can retry from the forward chain.
 */
class SendForwardEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public ApplicationForward $forward) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(GmailSender $gmail, ForwardEmailBuilder $builder): void
    {
        $forward = $this->forward;

        if (! in_array($forward->email_status, [ForwardEmailStatus::PendingEmail, ForwardEmailStatus::Failed], true)) {
            return;
        }

        $forward->increment('email_attempts');

        try {
            $messageId = $gmail->send($forward->sender, $builder->build($forward));
        } catch (GmailNotConnected $exception) {
            $this->markFailed($exception->getMessage());

            return;
        } catch (ConnectionException|RequestException $exception) {
            if ($this->isTemporary($exception) && $this->attempts() < $this->tries) {
                $forward->update(['email_error' => $this->reason($exception)]);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 300);

                return;
            }

            $this->markFailed($this->reason($exception));

            return;
        }

        $forward->update([
            'email_status' => ForwardEmailStatus::Sent,
            'email_error' => null,
            'emailed_at' => now(),
            'gmail_message_id' => $messageId,
        ]);

        activity()
            ->causedBy($forward->sender)
            ->performedOn($forward)
            ->withProperties(['application_id' => $forward->application_id, 'to_role' => $forward->to_role->value])
            ->log('forward email sent');
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed(__('The email could not be sent. Retry it from the forward chain.'));
    }

    private function markFailed(string $reason): void
    {
        $this->forward->update([
            'email_status' => ForwardEmailStatus::Failed,
            'email_error' => Str::limit($reason, 480),
        ]);

        activity()
            ->causedBy($this->forward->sender)
            ->performedOn($this->forward)
            ->withProperties(['application_id' => $this->forward->application_id, 'reason' => $reason])
            ->log('forward email failed');
    }

    private function isTemporary(ConnectionException|RequestException $exception): bool
    {
        return $exception instanceof ConnectionException
            || $exception->response->status() === 429
            || $exception->response->serverError();
    }

    private function reason(ConnectionException|RequestException $exception): string
    {
        if ($exception instanceof ConnectionException) {
            return __('Could not reach Gmail. Check the connection and retry.');
        }

        $googleMessage = $exception->response->json('error.message');

        return __('Gmail refused the email (HTTP :status)', ['status' => $exception->response->status()])
            .(is_string($googleMessage) && $googleMessage !== '' ? ': '.$googleMessage : '.');
    }
}
