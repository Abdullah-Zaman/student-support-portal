<?php

use App\Enums\TimetableClassType;
use App\Support\Timetable\ParsedClass;
use App\Support\Timetable\TimetableParser;

/**
 * @param  list<ParsedClass>  $classes
 */
function parsedClassAt(array $classes, string $sheet, string $cell): ParsedClass
{
    foreach ($classes as $class) {
        if ($class->sheet === $sheet && $class->cell === $cell) {
            return $class;
        }
    }

    throw new RuntimeException("No class parsed at {$sheet}!{$cell}.");
}

test('the generated workbook parses rooms, floors, merges, overrides and combined sections', function () {
    $parsed = (new TimetableParser)->parse(makeTimetableWorkbook());

    expect($parsed->classes)->toHaveCount(6);

    $merged = parsedClassAt($parsed->classes, 'Time Table', 'D4');
    expect($merged->day)->toBe(1)
        ->and([$merged->startsAt, $merged->endsAt])->toBe(['08:00', '10:40'])
        ->and($merged->room)->toBe('Lecture Room # 22')
        ->and($merged->floor)->toBe('1st Floor')
        ->and($merged->course)->toBe('Software Engineering')
        ->and($merged->teacher)->toBe('Mr. Muhammad Hamza')
        ->and($merged->sections)->toBe(['BSSE-3A', 'BSSE-3B']);

    $lab = parsedClassAt($parsed->classes, 'Time Table', 'F4');
    expect($lab->type)->toBe(TimetableClassType::Lab)
        ->and([$lab->startsAt, $lab->endsAt])->toBe(['10:45', '12:05']);

    $combined = parsedClassAt($parsed->classes, 'Time Table', 'G4');
    expect([$combined->startsAt, $combined->endsAt])->toBe(['13:30', '15:30'])
        ->and($combined->teacher)->toBe('Mr. Zahid Ali')
        ->and($combined->sections)->toBe(['BSSE-1A', 'BSSE-1B']);

    $theoryLab = parsedClassAt($parsed->classes, 'Time Table', 'D5');
    expect($theoryLab->type)->toBe(TimetableClassType::TheoryLab)
        ->and($theoryLab->course)->toBe('Applied Physics (Theory + Lab)')
        ->and($theoryLab->floor)->toBe('1st Floor');

    $weekend = parsedClassAt($parsed->classes, '1M Timetable', 'C3');
    expect($weekend->day)->toBe(6)
        ->and([$weekend->startsAt, $weekend->endsAt])->toBe(['09:00', '11:40'])
        ->and($weekend->course)->toBe('Data Visualization')
        ->and($weekend->floor)->toBeNull()
        ->and($weekend->sections)->toBe(['BSAI-7C', 'BSAI-8A', 'BSDS-8A']);

    expect($parsed->unparsedCells)->toBe([[
        'sheet' => 'Time Table',
        'cell' => 'F5',
        'text' => 'Lab closed for maintenance',
        'reason' => 'No section (like BSSE-3A) found.',
    ]]);
});

test('the Fall-26 department timetable parses every class with the right days and times', function () {
    $parsed = (new TimetableParser)->parse(fixturePath());

    expect($parsed->classes)->toHaveCount(330)
        ->and($parsed->unparsedCells)->toBe([]);

    $perDay = array_count_values(array_map(fn (ParsedClass $class): int => $class->day, $parsed->classes));
    ksort($perDay);
    expect($perDay)->toBe([1 => 56, 2 => 58, 3 => 58, 4 => 61, 5 => 51, 6 => 23, 7 => 23]);

    $slot = parsedClassAt($parsed->classes, 'Time Table', 'E12');
    expect([$slot->day, $slot->startsAt, $slot->endsAt])->toBe([1, '09:20', '10:40'])
        ->and($slot->sections)->toBe(['BSSE-1B']);

    $twoSlots = parsedClassAt($parsed->classes, 'Time Table', 'J8');
    expect([$twoSlots->startsAt, $twoSlots->endsAt])->toBe(['16:10', '18:50']);

    $override = parsedClassAt($parsed->classes, 'Time Table', 'D9');
    expect([$override->startsAt, $override->endsAt])->toBe(['08:00', '10:00'])
        ->and($override->floor)->toBe('1st Floor');

    $acrossSlots = parsedClassAt($parsed->classes, 'Time Table', 'E35');
    expect([$acrossSlots->day, $acrossSlots->startsAt, $acrossSlots->endsAt])->toBe([2, '10:00', '12:00']);

    $wholeSemester = parsedClassAt($parsed->classes, 'Time Table', 'H29');
    expect($wholeSemester->sections)->toBe(['BSSE-1A', 'BSSE-1B', 'BSSE-1C', 'BSSE-1D']);

    $friday = parsedClassAt($parsed->classes, 'Time Table', 'I78');
    expect([$friday->day, $friday->startsAt, $friday->endsAt])->toBe([5, '14:00', '16:30'])
        ->and($friday->type)->toBe(TimetableClassType::TheoryLab);

    $sunday = parsedClassAt($parsed->classes, '1M Timetable', 'C20');
    expect([$sunday->day, $sunday->startsAt, $sunday->endsAt])->toBe([7, '09:00', '11:40'])
        ->and($sunday->sections)->toBe(['BSSE-6A']);
})->skip(fn () => ! file_exists(fixturePath()), 'The Fall-26 timetable fixture is not committed; copy it to tests/Fixtures/timetable-fall-26.xlsx to run this test.');

function fixturePath(): string
{
    return dirname(__DIR__, 3).'/Fixtures/timetable-fall-26.xlsx';
}
