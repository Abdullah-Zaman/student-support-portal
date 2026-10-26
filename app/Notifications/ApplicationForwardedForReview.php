<?php

namespace App\Notifications;

use App\Enums\ForwardTarget;
use App\Models\Application;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Tells the student where their application went, as a database
 * notification only. It never carries the forward note or any remark.
 */
class ApplicationForwardedForReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application, public ForwardTarget $target) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'application_no' => $this->application->application_no,
            'message' => __('Your application :number has been forwarded to the :target for review.', [
                'number' => $this->application->application_no,
                'target' => $this->target->label(),
            ]),
            'url' => route('student.applications.show', $this->application),
        ];
    }
}
