<?php

namespace App\Enums;

enum ApplicationEventType: string
{
    case Submitted = 'submitted';
    case StatusChanged = 'status_changed';
    case PriorityChanged = 'priority_changed';
    case MessageSent = 'message_sent';
    case InfoRequested = 'info_requested';
    case DocumentRequested = 'document_requested';
    case StudentResponded = 'student_responded';
    case AttachmentUploaded = 'attachment_uploaded';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Forwarded = 'forwarded';
}
