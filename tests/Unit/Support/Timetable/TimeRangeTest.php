<?php

use App\Support\Timetable\TimeRange;

test('slot headers are read as 24-hour times, with 1 to 7 o\'clock in the afternoon', function (string $header, string $start, string $end) {
    $range = TimeRange::fromHeader($header);

    expect($range?->start)->toBe($start)
        ->and($range?->end)->toBe($end);
})->with([
    ['8:00-9:20', '08:00', '09:20'],
    ['12:05 -01:25', '12:05', '13:25'],
    ['05:30 - 06:50', '17:30', '18:50'],
    ['09:00 - 11:40', '09:00', '11:40'],
]);

test('text that is not only a time range is not a slot header', function (string $text) {
    expect(TimeRange::fromHeader($text))->toBeNull();
})->with(['Jumma Prayer', 'Monday', "Islamic Studies\nBSAI-3D\n(10:00 - 12:00)"]);

test('override lines are read despite typos and am/pm markers', function (string $line, string $start, string $end) {
    $range = TimeRange::find($line);

    expect($range?->start)->toBe($start)
        ->and($range?->end)->toBe($end);
})->with([
    ['(08:00 - 10:00)', '08:00', '10:00'],
    ['(01:30 pm to 03:10 pm)', '13:30', '15:10'],
    ['(01:-30 - 03:30)', '13:30', '15:30'],
    ['(03:30 -06:10)', '15:30', '18:10'],
    ['(10:45 am to 12:35 pm)', '10:45', '12:35'],
    ['(09:40 am - 10:40 am)', '09:40', '10:40'],
]);

test('a range that ends before it starts is rejected', function () {
    expect(TimeRange::find('(04:00 - 02:00 pm)'))->toBeNull();
});
