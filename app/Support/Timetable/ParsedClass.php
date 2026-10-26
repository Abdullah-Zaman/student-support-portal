<?php

namespace App\Support\Timetable;

use App\Enums\TimetableClassType;

/**
 * One class read from a timetable cell, before it is stored.
 */
final readonly class ParsedClass
{
    /**
     * @param  list<string>  $sections
     */
    public function __construct(
        public int $day,
        public string $startsAt,
        public string $endsAt,
        public string $room,
        public ?string $floor,
        public string $course,
        public TimetableClassType $type,
        public ?string $teacher,
        public string $rawSections,
        public array $sections,
        public string $sheet,
        public string $cell,
    ) {}
}
