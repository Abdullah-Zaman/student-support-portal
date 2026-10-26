<?php

namespace App\Notifications;

use App\Models\Application;

class AdminMessageReceived extends ApplicationStudentNotification
{
    public function __construct(Application $application, public string $body)
    {
        parent::__construct($application);
    }

    protected function subject(): string
    {
        return __('New message');
    }

    protected function message(): string
    {
        return __('The Admin Office sent you a message on :number.', [
            'number' => $this->application->application_no,
        ]);
    }

    protected function adminMessage(): ?string
    {
        return $this->body;
    }
}
