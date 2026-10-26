<?php

namespace App\Support\Timetable;

/**
 * A start and end time read from a timetable header ("8:00-9:20") or a
 * cell's override line ("(01:30 pm to 03:10 pm)"). The sheet writes
 * 12-hour times, mostly without am/pm, so an hour from 1 to 7 is read
 * as afternoon: classes run from 8 AM to about 7 PM.
 */
final readonly class TimeRange
{
    private const PATTERN = '/(\d{1,2})\s*[:.]\s*(\d{2})\s*(am|pm)?\s*-\s*(\d{1,2})\s*[:.]\s*(\d{2})\s*(am|pm)?/';

    public function __construct(
        public string $start,
        public string $end,
    ) {}

    /**
     * Parse text that holds only a time range (a slot header).
     */
    public static function fromHeader(string $text): ?self
    {
        $normalized = self::normalize($text);

        if (! preg_match(self::PATTERN, $normalized, $matches) || $matches[0] !== $normalized) {
            return null;
        }

        return self::fromMatches($matches);
    }

    /**
     * Parse a time range anywhere in a line of text (an override line).
     */
    public static function find(string $text): ?self
    {
        if (! preg_match(self::PATTERN, self::normalize($text), $matches)) {
            return null;
        }

        return self::fromMatches($matches);
    }

    /**
     * Lowercase, drop brackets, turn "to" into a dash and fix the typos
     * seen in real sheets ("01:-30", "12:05 -01:25").
     */
    private static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['(', ')', '–', '—'], ['', '', '-', '-'], $text);
        $text = (string) preg_replace('/\s+to\s+/', ' - ', $text);
        $text = (string) preg_replace('/:\s*-\s*(\d)/', ':$1', $text);
        $text = (string) preg_replace('/\b(a|p)\.?m\.?\b/', '$1m', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * @param  array<int, string>  $matches
     */
    private static function fromMatches(array $matches): ?self
    {
        $start = self::toMinutes((int) $matches[1], (int) $matches[2], $matches[3] ?? '');
        $end = self::toMinutes((int) $matches[4], (int) $matches[5], $matches[6] ?? '');

        if ($start === null || $end === null || $end <= $start) {
            return null;
        }

        return new self(self::format($start), self::format($end));
    }

    private static function toMinutes(int $hour, int $minute, string $meridiem): ?int
    {
        if ($hour < 1 || $hour > 12 || $minute > 59) {
            return null;
        }

        $hour = match (true) {
            $meridiem === 'am' => $hour === 12 ? 0 : $hour,
            $meridiem === 'pm' => $hour === 12 ? 12 : $hour + 12,
            $hour <= 7 => $hour + 12,
            default => $hour,
        };

        return $hour * 60 + $minute;
    }

    private static function format(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
