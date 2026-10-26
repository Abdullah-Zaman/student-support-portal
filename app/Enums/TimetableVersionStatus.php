<?php

namespace App\Enums;

enum TimetableVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
