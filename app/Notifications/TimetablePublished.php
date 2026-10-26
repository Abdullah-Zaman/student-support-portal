<?php

namespace App\Notifications;

use App\Models\TimetableVersion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Students get a database notification only, no email: emailing every
 * student of a department at once would hit Gmail's sending limits.
 */
class TimetablePublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public TimetableVersion $version) {}

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
            'timetable_version_id' => $this->version->id,
            'message' => __('A new timetable has been published. Check your classes.'),
            'url' => route('student.timetable'),
        ];
    }
}
