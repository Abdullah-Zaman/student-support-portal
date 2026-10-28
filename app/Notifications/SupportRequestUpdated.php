<?php

namespace App\Notifications;

use App\Enums\UserStatus;
use App\Models\SupportRequest;
use App\Models\User;
use App\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The reporter is told, in the portal and by email, when the Super Admin
 * sets their request in progress, replies, or resolves it. The reply or
 * resolution note is in the email, since it was written for them.
 */
class SupportRequestUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public const IN_PROGRESS = 'in_progress';

    public const REPLIED = 'replied';

    public const RESOLVED = 'resolved';

    public function __construct(public SupportRequest $supportRequest, public string $event) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return $notifiable->status === UserStatus::Suspended ? ['database'] : ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->message());

        if (filled($this->supportRequest->resolution_note)) {
            $message->line(__('Note from the Super Admin:'))->line($this->supportRequest->resolution_note);
        }

        $message->action(__('View your request'), $this->url());

        return BrandedMailMessage::finalize($message, $this->subject());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'support_request_id' => $this->supportRequest->id,
            'message' => $this->message(),
            'url' => $this->url(),
        ];
    }

    private function subject(): string
    {
        return match ($this->event) {
            self::RESOLVED => __('Your support request was resolved'),
            self::IN_PROGRESS => __('Your support request is in progress'),
            default => __('Update on your support request'),
        };
    }

    private function message(): string
    {
        return match ($this->event) {
            self::RESOLVED => __('Your support request ":subject" was resolved.', ['subject' => $this->supportRequest->subject]),
            self::IN_PROGRESS => __('Your support request ":subject" is now in progress.', ['subject' => $this->supportRequest->subject]),
            default => __('The Super Admin replied to your support request ":subject".', ['subject' => $this->supportRequest->subject]),
        };
    }

    private function url(): string
    {
        return route('support.show', $this->supportRequest);
    }
}
