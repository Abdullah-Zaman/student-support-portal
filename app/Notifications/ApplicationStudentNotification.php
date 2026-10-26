<?php

namespace App\Notifications;

use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\User;
use App\Support\BrandedMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Shared shape for every notification sent to a student about their own
 * application: a database row and a branded, queued email — unless the
 * student's account has been suspended, in which case only the database
 * row is kept (no mail is ever sent to a suspended account).
 */
abstract class ApplicationStudentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application) {}

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        if ($notifiable->status === UserStatus::Suspended) {
            return ['database'];
        }

        return ['database', 'mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->markdown('notifications::email', [
                'applicationNo' => $this->application->application_no,
                'applicationSubject' => $this->application->subject,
                'adminMessage' => $this->adminMessage(),
            ])
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->message())
            ->action(__('View application'), route('student.applications.show', $this->application));

        foreach ($this->extraLines() as $line) {
            $message->line($line);
        }

        return BrandedMailMessage::finalize($message, $this->subject());
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_no' => $this->application->application_no,
            'message' => $this->message(),
            'url' => route('student.applications.show', $this->application),
        ];
    }

    abstract protected function subject(): string;

    abstract protected function message(): string;

    /**
     * Extra mail-only lines (e.g. a resolution note) appended after the
     * main message — never shown for the database/bell version, since
     * that's meant to stay a short one-liner.
     *
     * @return array<int, string>
     */
    protected function extraLines(): array
    {
        return [];
    }

    /**
     * The Admin Office's own words (a message or an info/document
     * request), shown in a highlighted box above the button. Mail only.
     */
    protected function adminMessage(): ?string
    {
        return null;
    }
}
