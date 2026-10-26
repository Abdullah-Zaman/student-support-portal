<?php

namespace App\Support\Timetable;

use App\Enums\TimetableClassType;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Reads a department timetable workbook into structured classes.
 *
 * Every sheet is scanned for day blocks. A block starts at a header row
 * that holds two or more time-slot cells ("8:00-9:20"); the day comes
 * from that row (or the one above it) left of the first slot. The rows
 * below it are rooms: the room name and its floor (often merged down
 * over several rooms) sit left of the slots, and each non-empty slot
 * cell is a class. A cell merged across slots is one longer class, and
 * an explicit time line in a cell ("(08:00 - 10:00)") overrides the
 * slot times. A class cell holds the course, then the sections, then
 * the teacher(s); extra lines such as "Combined" are allowed.
 */
final class TimetableParser
{
    private const DAYS = [
        'monday' => 1,
        'tuesday' => 2,
        'wednesday' => 3,
        'thursday' => 4,
        'friday' => 5,
        'saturday' => 6,
        'sunday' => 7,
    ];

    /** @var array<int, array<int, string>> */
    private array $grid = [];

    /** @var array<int, array<int, array{int, int}>> top-left [row][col] => [lastCol, lastRow] */
    private array $mergeStarts = [];

    /** @var array<int, array<int, array{int, int}>> covered [row][col] => [topRow, topCol] */
    private array $mergeOwners = [];

    /**
     * @var list<array{day: int, startsAt: string, endsAt: string, room: string, floor: ?string, course: string, teacher: ?string, rawSections: string, sheet: string, cell: string}>
     */
    private array $drafts = [];

    /** @var list<array{sheet: string, cell: string, text: string, reason: string}> */
    private array $unparsed = [];

    public function parse(string $path): ParsedTimetable
    {
        $this->drafts = [];
        $this->unparsed = [];

        $reader = new Xlsx;
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($path);

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $this->parseSheet($sheet);
        }

        $spreadsheet->disconnectWorksheets();

        return new ParsedTimetable($this->buildClasses(), $this->unparsed);
    }

    private function parseSheet(Worksheet $sheet): void
    {
        $sheetName = trim($sheet->getTitle());
        $this->loadGrid($sheet);

        $day = null;
        $slots = [];
        $firstSlotColumn = PHP_INT_MAX;

        $rows = array_keys($this->grid);
        sort($rows);

        foreach ($rows as $row) {
            $headerSlots = $this->headerSlots($row);

            if (count($headerSlots) >= 2) {
                $slots = $headerSlots;
                $firstSlotColumn = min(array_keys($slots));
                $day = $this->detectDay($row, $firstSlotColumn) ?? $this->detectDay($row - 1, $firstSlotColumn);

                if ($day === null) {
                    $this->addUnparsed($sheetName, $row, $firstSlotColumn, implode(' | ', $this->grid[$row]), 'Could not tell which day this block of time slots belongs to.');
                }

                continue;
            }

            if ($slots === [] || $day === null) {
                continue;
            }

            $this->parseRoomRow($sheetName, $row, $day, $slots, $firstSlotColumn);
        }
    }

    private function loadGrid(Worksheet $sheet): void
    {
        $this->grid = [];
        $this->mergeStarts = [];
        $this->mergeOwners = [];

        foreach ($sheet->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                $text = $value instanceof RichText ? $value->getPlainText() : (is_scalar($value) ? (string) $value : '');
                $text = trim(str_replace(["\r\n", "\r", "\u{00A0}"], ["\n", "\n", ' '], $text));

                if ($text !== '') {
                    $this->grid[$row->getRowIndex()][Coordinate::columnIndexFromString($cell->getColumn())] = $text;
                }
            }
        }

        foreach ($sheet->getMergeCells() as $range) {
            [[$firstColumn, $firstRow], [$lastColumn, $lastRow]] = Coordinate::rangeBoundaries($range);
            $firstColumn = (int) $firstColumn;
            $lastColumn = (int) $lastColumn;

            $this->mergeStarts[$firstRow][$firstColumn] = [$lastColumn, $lastRow];

            for ($row = $firstRow; $row <= $lastRow; $row++) {
                for ($column = $firstColumn; $column <= $lastColumn; $column++) {
                    if ($row !== $firstRow || $column !== $firstColumn) {
                        $this->mergeOwners[$row][$column] = [$firstRow, $firstColumn];
                    }
                }
            }
        }
    }

    /**
     * @return array<int, TimeRange> column => slot
     */
    private function headerSlots(int $row): array
    {
        $slots = [];

        foreach ($this->grid[$row] ?? [] as $column => $text) {
            $range = TimeRange::fromHeader($text);

            if ($range !== null) {
                $slots[$column] = $range;
            }
        }

        ksort($slots);

        return $slots;
    }

    private function detectDay(int $row, int $beforeColumn): ?int
    {
        for ($column = 1; $column < $beforeColumn; $column++) {
            $day = $this->dayFromText($this->valueAt($row, $column));

            if ($day !== null) {
                return $day;
            }
        }

        return null;
    }

    /**
     * Tolerates spacing, case and small typos ("Wedensday", "Thurday").
     */
    private function dayFromText(?string $text): ?int
    {
        $word = (string) preg_replace('/[^a-z]/', '', mb_strtolower((string) $text));

        if ($word === '') {
            return null;
        }

        $closestDay = null;
        $closestDistance = 3;

        foreach (self::DAYS as $name => $number) {
            if ($word === $name || $word === substr($name, 0, 3)) {
                return $number;
            }

            $distance = levenshtein($word, $name);

            if ($distance < $closestDistance) {
                $closestDay = $number;
                $closestDistance = $distance;
            }
        }

        return $closestDay;
    }

    /**
     * @param  array<int, TimeRange>  $slots
     */
    private function parseRoomRow(string $sheetName, int $row, int $day, array $slots, int $firstSlotColumn): void
    {
        $room = null;
        $floor = null;

        for ($column = 1; $column < $firstSlotColumn; $column++) {
            $text = $this->valueAt($row, $column);

            if ($text === null || $this->dayFromText($text) !== null) {
                continue;
            }

            if (preg_match('/\bfloor\b/i', $text)) {
                $floor = $this->normalizeFloor($text);
            } elseif ($room === null && ! preg_match('/^rooms?$/i', $text)) {
                $room = (string) preg_replace('/\s+/', ' ', $text);
            }
        }

        foreach ($this->grid[$row] as $column => $text) {
            if ($column < $firstSlotColumn) {
                continue;
            }

            if ($room === null) {
                $this->addUnparsed($sheetName, $row, $column, $text, 'No room name found for this row.');

                continue;
            }

            if (! isset($slots[$column])) {
                $this->addUnparsed($sheetName, $row, $column, $text, 'This cell is not under a time slot.');

                continue;
            }

            $lastColumn = $this->mergeStarts[$row][$column][0] ?? $column;
            $endSlot = $slots[$column];

            foreach ($slots as $slotColumn => $slot) {
                if ($slotColumn <= $lastColumn) {
                    $endSlot = $slot;
                }
            }

            $this->parseClassCell($sheetName, $row, $column, $text, $day, $room, $floor, $slots[$column]->start, $endSlot->end);
        }
    }

    private function parseClassCell(string $sheetName, int $row, int $column, string $text, int $day, string $room, ?string $floor, string $startsAt, string $endsAt): void
    {
        $lines = [];
        $override = null;

        foreach (explode("\n", $text) as $line) {
            $line = trim((string) preg_replace('/\s+/', ' ', $line));

            if ($line === '' || preg_match('/^combined$/i', $line)) {
                continue;
            }

            if ($this->isTimeLine($line)) {
                $override = TimeRange::find($line);

                continue;
            }

            $lines[] = $line;
        }

        $sectionIndex = null;

        foreach ($lines as $index => $line) {
            if (SectionExpander::containsSection($line)) {
                $sectionIndex = $index;

                break;
            }
        }

        if ($sectionIndex === null) {
            $this->addUnparsed($sheetName, $row, $column, $text, 'No section (like BSSE-3A) found.');

            return;
        }

        $sectionLine = $lines[$sectionIndex];
        $inlineCourse = SectionExpander::textBeforeFirstSection($sectionLine);
        $course = trim(implode(' ', array_slice($lines, 0, $sectionIndex)).' '.$inlineCourse);
        $rawSections = trim(substr($sectionLine, strlen($inlineCourse)));
        $teachers = array_slice($lines, $sectionIndex + 1);

        if ($course === '') {
            $this->addUnparsed($sheetName, $row, $column, $text, 'No course name found.');

            return;
        }

        $course = $this->normalizeCourse($course);

        $this->drafts[] = [
            'day' => $day,
            'startsAt' => $override !== null ? $override->start : $startsAt,
            'endsAt' => $override !== null ? $override->end : $endsAt,
            'room' => $room,
            'floor' => $floor,
            'course' => $course,
            'teacher' => $teachers !== [] ? implode(', ', $teachers) : null,
            'rawSections' => $rawSections,
            'sheet' => $sheetName,
            'cell' => Coordinate::stringFromColumnIndex($column).$row,
        ];
    }

    /**
     * A line that is only a time range, e.g. "(08:00 - 10:00)" or
     * "(01:30 pm to 03:10 pm)".
     */
    private function isTimeLine(string $line): bool
    {
        $leftover = (string) preg_replace('/\b(am|pm|to)\b|[\d\s:.\-()–—]/i', '', $line);

        return $leftover === '' && TimeRange::find($line) !== null;
    }

    /**
     * Fix common typos and spacing: "(Thoery )" becomes "(Theory)".
     */
    private function normalizeCourse(string $course): string
    {
        $course = (string) preg_replace('/\bth(?:oe|eo)ry\b|\btheroy\b/i', 'Theory', $course);
        $course = (string) preg_replace('/\(\s*/', '(', $course);
        $course = (string) preg_replace('/\s*\)/', ')', $course);

        return trim((string) preg_replace('/\s+/', ' ', $course));
    }

    /**
     * "Ist Floor " becomes "1st Floor"; "Ground floor" becomes "Ground Floor".
     */
    private function normalizeFloor(string $floor): string
    {
        $floor = trim((string) preg_replace('/\s+/', ' ', $floor));

        return match (true) {
            (bool) preg_match('/ground/i', $floor) => 'Ground Floor',
            (bool) preg_match('/^(1|i|first)/i', $floor) => '1st Floor',
            (bool) preg_match('/^(2|second)/i', $floor) => '2nd Floor',
            (bool) preg_match('/^(3|third)/i', $floor) => '3rd Floor',
            default => ucwords(mb_strtolower($floor)),
        };
    }

    /**
     * The value of a cell, or of the merged range that covers it.
     */
    private function valueAt(int $row, int $column): ?string
    {
        [$row, $column] = $this->mergeOwners[$row][$column] ?? [$row, $column];

        return $this->grid[$row][$column] ?? null;
    }

    private function addUnparsed(string $sheetName, int $row, int $column, string $text, string $reason): void
    {
        $this->unparsed[] = [
            'sheet' => $sheetName,
            'cell' => Coordinate::stringFromColumnIndex($column).$row,
            'text' => trim((string) preg_replace('/\s*\n\s*/', ' / ', $text)),
            'reason' => $reason,
        ];
    }

    /**
     * Expand sections once every cell is read, so a whole-semester token
     * ("BSSE-1") can use the lettered sections found anywhere in the file.
     *
     * @return list<ParsedClass>
     */
    private function buildClasses(): array
    {
        $knownSections = [];

        foreach ($this->drafts as $draft) {
            array_push($knownSections, ...SectionExpander::letteredSections($draft['rawSections']));
        }

        $knownSections = array_values(array_unique($knownSections));

        return array_map(fn (array $draft): ParsedClass => new ParsedClass(
            day: $draft['day'],
            startsAt: $draft['startsAt'],
            endsAt: $draft['endsAt'],
            room: $draft['room'],
            floor: $draft['floor'],
            course: $draft['course'],
            type: TimetableClassType::fromCourseName($draft['course']),
            teacher: $draft['teacher'],
            rawSections: $draft['rawSections'],
            sections: SectionExpander::expand($draft['rawSections'], $knownSections),
            sheet: $draft['sheet'],
            cell: $draft['cell'],
        ), $this->drafts);
    }
}
