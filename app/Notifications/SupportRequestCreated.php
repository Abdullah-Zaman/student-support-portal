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
 * The Super Admin is told, in the portal and by email, when a staff
 * member opens a support request. The email names the reporter and the
 * subject only; the message itself stays in the portal.
 */
class SupportRequestCreated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public SupportRequest $supportRequest) {}

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
            ->line($this->message())
            ->line(__('Category: :category', ['category' => __($this->supportRequest->category->label())]))
            ->action(__('Open support request'), $this->url());

        return BrandedMailMessage::finalize($message, __('New support request'));
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

    private function message(): string
    {
        return __(':name sent a support request: :subject', [
            'name' => $this->supportRequest->reporter->name,
            'subject' => $this->supportRequest->subject,
        ]);
    }

    private function url(): string
    {
        return route('super-admin.support.show', $this->supportRequest);
    }
}
