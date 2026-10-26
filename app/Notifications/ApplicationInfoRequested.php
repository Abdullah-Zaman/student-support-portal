<?php

namespace App\Notifications;

use App\Enums\MessageType;
use App\Models\Application;

class ApplicationInfoRequested extends ApplicationStudentNotification
{
    public function __construct(Application $application, public MessageType $requestType, public string $body)
    {
        parent::__construct($application);
    }

    protected function subject(): string
    {
        return $this->requestType === MessageType::DocumentRequest
            ? __('Document requested')
            : __('Information requested');
    }

    protected function message(): string
    {
        return $this->requestType === MessageType::DocumentRequest
            ? __('The Admin Office needs a document from you on :number.', ['number' => $this->application->application_no])
            : __('The Admin Office needs more information from you on :number.', ['number' => $this->application->application_no]);
    }

    protected function adminMessage(): ?string
    {
        return $this->body;
    }
}
