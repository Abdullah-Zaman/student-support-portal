<?php

namespace App\Support\Timetable;

/**
 * The result of parsing a timetable workbook: the classes it found and
 * the non-empty cells it could not read, for the admin's preview.
 */
final readonly class ParsedTimetable
{
    /**
     * @param  list<ParsedClass>  $classes
     * @param  list<array{sheet: string, cell: string, text: string, reason: string}>  $unparsedCells
     */
    public function __construct(
        public array $classes,
        public array $unparsedCells,
    ) {}
}
