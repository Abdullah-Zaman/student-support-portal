<?php

namespace App\Support\Timetable;

/**
 * Turns the section text of a timetable cell into individual sections.
 *
 * "BSAI-4A/BSDS-4A", "BSSE-3A & BSSE-3B" and "BSSE-3C + BSSE-3D" list
 * sections one by one; "BSAI/BSDS-8A" shares one semester and letter
 * between programs; "BSSE-1" (or "BSAI-1Combined") is a combined class
 * for the whole semester, expanded to every lettered section of that
 * semester found elsewhere in the same timetable.
 */
final class SectionExpander
{
    private const TOKEN = '/\b((?:BS[A-Z]{2,4}\s*\/\s*)*BS[A-Z]{2,4})\s*-\s*(\d{1,2})(?:([A-Z])(?![A-Z]))?/';

    /**
     * Whether the text contains at least one section token.
     */
    public static function containsSection(string $text): bool
    {
        return (bool) preg_match(self::TOKEN, strtoupper($text));
    }

    /**
     * The text before the first section token, for cells that put the
     * course and sections on one line.
     */
    public static function textBeforeFirstSection(string $text): string
    {
        if (! preg_match(self::TOKEN, strtoupper($text), $matches, PREG_OFFSET_CAPTURE)) {
            return $text;
        }

        return trim(substr($text, 0, $matches[0][1]));
    }

    /**
     * Sections written with a letter ("BSSE-3A"), not expanded further.
     *
     * @return list<string>
     */
    public static function letteredSections(string $text): array
    {
        return array_values(array_filter(
            self::expand($text),
            fn (string $section): bool => (bool) preg_match('/\d[A-Z]$/', $section),
        ));
    }

    /**
     * @param  list<string>  $knownSections  lettered sections in the same timetable, for expanding whole-semester tokens
     * @return list<string>
     */
    public static function expand(string $text, array $knownSections = []): array
    {
        preg_match_all(self::TOKEN, strtoupper($text), $matches, PREG_SET_ORDER);

        $sections = [];

        foreach ($matches as $match) {
            $programs = array_map('trim', explode('/', $match[1]));
            $semester = (int) $match[2];
            $letter = $match[3] ?? '';

            foreach ($programs as $program) {
                if ($letter !== '') {
                    $sections[] = "{$program}-{$semester}{$letter}";

                    continue;
                }

                $wholeSemester = array_filter(
                    $knownSections,
                    fn (string $known): bool => (bool) preg_match('/^'.preg_quote("{$program}-{$semester}", '/').'[A-Z]$/', $known),
                );

                array_push($sections, ...($wholeSemester !== [] ? $wholeSemester : ["{$program}-{$semester}"]));
            }
        }

        $sections = array_values(array_unique($sections));
        sort($sections, SORT_NATURAL);

        return $sections;
    }

    /**
     * "BSSE-7B" becomes ['program_code' => 'BSSE', 'semester' => 7].
     *
     * @return array{program_code: string, semester: int}
     */
    public static function describe(string $section): array
    {
        preg_match('/^([A-Z]+)-(\d{1,2})/', $section, $matches);

        return [
            'program_code' => $matches[1] ?? $section,
            'semester' => (int) ($matches[2] ?? 0),
        ];
    }
}
