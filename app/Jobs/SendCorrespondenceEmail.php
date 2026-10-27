<?php

namespace App\Jobs;

use App\Enums\CorrespondenceEmailStatus;
use App\Models\CorrespondenceMessage;
use App\Support\Correspondence\CorrespondenceEmailBuilder;
use App\Support\Gmail\GmailNotConnected;
use App\Support\Gmail\GmailSender;
use App\Support\Gmail\GmailSentMessage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Throwable;

/**
 * Emails one correspondence message from the sender's own Gmail and
 * records the outcome on it. Only a message in "sending" is sent, and
 * only one job per message runs at a time, so it never goes out twice.
 * The sender's earlier Gmail thread for this application is reused so
 * their Sent folder keeps one conversation. Temporary Gmail trouble is
 * retried; a lost Gmail connection puts the message back to "pending:
 * connect Gmail"; anything else fails with a reason the sender can retry.
 */
class SendCorrespondenceEmail implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public CorrespondenceMessage $message) {}

    public function uniqueId(): string
    {
        return (string) $this->message->id;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(GmailSender $gmail, CorrespondenceEmailBuilder $builder): void
    {
        $message = $this->message->refresh();

        if ($message->email_status !== CorrespondenceEmailStatus::Sending || $message->sender === null) {
            return;
        }

        $message->increment('email_attempts');

        try {
            $sent = $this->send($gmail, $builder, $message);
        } catch (GmailNotConnected $exception) {
            $message->update(['email_status' => CorrespondenceEmailStatus::AwaitingGmail, 'email_error' => $exception->getMessage()]);

            return;
        } catch (ConnectionException|RequestException $exception) {
            if ($this->isTemporary($exception) && $this->attempts() < $this->tries) {
                $message->update(['email_error' => $this->reason($exception)]);
                $this->release($this->backoff()[$this->attempts() - 1] ?? 300);

                return;
            }

            $this->markFailed($this->reason($exception));

            return;
        }

        $message->update([
            'email_status' => CorrespondenceEmailStatus::Sent,
            'email_error' => null,
            'emailed_at' => now(),
            'gmail_message_id' => $sent->id,
            'gmail_thread_id' => $sent->threadId,
        ]);

        activity()
            ->causedBy($message->sender)
            ->performedOn($message)
            ->withProperties(['application_id' => $message->application_id, 'to_party' => $message->to_party->value])
            ->log('correspondence email sent');
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed(__('The email could not be sent. Retry it from the correspondence thread.'));
    }

    /**
     * Join the sender's earlier Gmail thread for this application. If
     * Gmail no longer knows that thread (the sender deleted it), send
     * without it rather than fail.
     */
    private function send(GmailSender $gmail, CorrespondenceEmailBuilder $builder, CorrespondenceMessage $message): GmailSentMessage
    {
        $threadId = CorrespondenceMessage::query()
            ->where('application_id', $message->application_id)
            ->where('from_user_id', $message->from_user_id)
            ->whereNotNull('gmail_thread_id')
            ->where('id', '<', $message->id)
            ->latest('id')
            ->value('gmail_thread_id');

        try {
            return $gmail->send($message->sender, $builder->build($message), $threadId);
        } catch (RequestException $exception) {
            if ($threadId === null || ! in_array($exception->response->status(), [400, 404], true)) {
                throw $exception;
            }

            return $gmail->send($message->sender, $builder->build($message));
        }
    }

    private function markFailed(string $reason): void
    {
        $this->message->update([
            'email_status' => CorrespondenceEmailStatus::Failed,
            'email_error' => Str::limit($reason, 480),
        ]);

        activity()
            ->causedBy($this->message->sender)
            ->performedOn($this->message)
            ->withProperties(['application_id' => $this->message->application_id, 'reason' => $reason])
            ->log('correspondence email failed');
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
