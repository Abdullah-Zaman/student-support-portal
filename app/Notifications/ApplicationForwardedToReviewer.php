<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\ApplicationForward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * The HOD or Dean gets a database notification when an application is
 * forwarded to them. Email delivery comes in Phase D.
 */
class ApplicationForwardedToReviewer extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application, public ApplicationForward $forward) {}

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
            'message' => __(':number from :name was forwarded to you by the :role.', [
                'number' => $this->application->application_no,
                'name' => $this->application->student->user->name,
                'role' => $this->forward->from_role->label(),
            ]),
            'url' => route($this->forward->to_role->value.'.applications.show', $this->application),
        ];
    }
}
