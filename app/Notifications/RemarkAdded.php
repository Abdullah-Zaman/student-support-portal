<?php

namespace App\Notifications;

use App\Models\Application;
use App\Models\ApplicationRemark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Admin Officers get a database notification when the HOD or Dean adds
 * a remark to one of their department's applications.
 */
class RemarkAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application, public ApplicationRemark $remark) {}

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
            'message' => __('The :role added a remark on :number: :type.', [
                'role' => $this->remark->author_role->label(),
                'number' => $this->application->application_no,
                'type' => $this->remark->type->label(),
            ]),
            'url' => route('admin.applications.show', $this->application),
        ];
    }
}
