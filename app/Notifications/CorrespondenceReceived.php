<?php

namespace App\Notifications;

use App\Enums\CorrespondenceType;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * A staff member gets a database notification for every correspondence
 * message addressed to them (forward, reply, remark or a logged Registrar
 * reply). The email itself goes from the sender's own Gmail.
 */
class CorrespondenceReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Application $application, public CorrespondenceMessage $message) {}

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
        $number = $this->application->application_no;
        $from = $this->message->from_party->label();
        $routeName = $this->message->to_party->applicationRouteName();

        return [
            'application_id' => $this->application->id,
            'application_no' => $number,
            'message' => match ($this->message->type) {
                CorrespondenceType::Forward => __(':number from :name was forwarded to you by the :role.', [
                    'number' => $number,
                    'name' => $this->application->student->user->name,
                    'role' => $from,
                ]),
                CorrespondenceType::Reply => __('The :role replied on :number.', ['role' => $from, 'number' => $number]),
                CorrespondenceType::Remark => __('The :role added a remark on :number: :type.', [
                    'role' => $from,
                    'number' => $number,
                    'type' => $this->message->remark_type?->label(),
                ]),
                CorrespondenceType::LoggedReply => __('A Registrar reply on :number was logged by the Admin Office.', ['number' => $number]),
            },
            'url' => $routeName !== null ? route($routeName, $this->application).'#correspondence' : null,
        ];
    }
}
