<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\ApplicationForward;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Admin Officers get a database notification when an HOD or Dean passes
 * an application on (to the Dean or the Registrar).
 */
class ApplicationForwardedOnward extends Notification implements ShouldQueue
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
            'message' => __('The :from forwarded :number to the :to.', [
                'from' => $this->forward->from_role->label(),
                'number' => $this->application->application_no,
                'to' => $this->forward->to_role->label(),
            ]),
            'url' => route('admin.applications.show', $this->application),
        ];
    }
}
