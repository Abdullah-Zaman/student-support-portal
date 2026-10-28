<?php

namespace App\Enums;

enum SupportRequestCategory: string
{
    case StudentIssue = 'student_issue';
    case MyAccount = 'my_account';
    case SystemProblem = 'system_problem';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::StudentIssue => 'Student issue',
            self::MyAccount => 'My account',
            self::SystemProblem => 'System problem',
            self::Other => 'Other',
        };
    }
}
