<?php

namespace App\Notifications;

use App\Models\Application;

class ApplicationClosed extends ApplicationStudentNotification
{
    public function __construct(Application $application, public ?string $body = null)
    {
        parent::__construct($application);
    }

    protected function subject(): string
    {
        return __('Application closed');
    }

    protected function message(): string
    {
        return __(':number has been closed.', ['number' => $this->application->application_no]);
    }

    protected function adminMessage(): ?string
    {
        return $this->body;
    }
}
