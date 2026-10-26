<?php

use App\Support\Timetable\SectionExpander;

test('section strings expand into individual sections', function (string $text, array $expected) {
    $known = ['BSSE-1A', 'BSSE-1B', 'BSSE-1C', 'BSAI-1A', 'BSDS-1A'];

    expect(SectionExpander::expand($text, $known))->toBe($expected);
})->with([
    'slash between sections' => ['BSAI-4A/BSDS-4A', ['BSAI-4A', 'BSDS-4A']],
    'slash with spaces' => ['BSAI-2B / BSDS-2A', ['BSAI-2B', 'BSDS-2A']],
    'ampersand' => ['BSSE-3A & BSSE-3B', ['BSSE-3A', 'BSSE-3B']],
    'plus' => ['BSSE-3C + BSSE-3D', ['BSSE-3C', 'BSSE-3D']],
    'shared semester and letter' => ['BSAI/BSDS-8A', ['BSAI-8A', 'BSDS-8A']],
    'whole semester' => ['BSSE-1', ['BSSE-1A', 'BSSE-1B', 'BSSE-1C']],
    'whole semester written as Combined' => ['BSAI-1Combined/BSDS-1Combined', ['BSAI-1A', 'BSDS-1A']],
    'mixed separators' => ['BSAI-6A / BSDS-6A & BSSE-5E', ['BSAI-6A', 'BSDS-6A', 'BSSE-5E']],
    'lowercase and extra spaces' => ['bsse -7b', ['BSSE-7B']],
]);

test('a whole-semester token with no known sections is kept as written', function () {
    expect(SectionExpander::expand('BSSE-2'))->toBe(['BSSE-2']);
});

test('a section is described by its program code and semester', function () {
    expect(SectionExpander::describe('BSSE-7B'))->toBe(['program_code' => 'BSSE', 'semester' => 7]);
});
