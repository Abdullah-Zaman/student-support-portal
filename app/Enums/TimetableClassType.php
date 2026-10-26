<?php

namespace App\Enums;

enum TimetableClassType: string
{
    case Theory = 'theory';
    case Lab = 'lab';
    case TheoryLab = 'theory_lab';

    /**
     * Read from the course name as written in the timetable sheet:
     * "(Theory + Lab)" (or a typo of it) is a combined class, "(Lab)" is
     * a lab and anything else is theory.
     */
    public static function fromCourseName(string $course): self
    {
        $hasLab = (bool) preg_match('/\blabs?\b/i', $course);
        $hasTheory = (bool) preg_match('/\bth[eo]{2}ry\b|\btheroy\b/i', $course);

        return match (true) {
            $hasLab && $hasTheory => self::TheoryLab,
            $hasLab => self::Lab,
            default => self::Theory,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Theory => 'Theory',
            self::Lab => 'Lab',
            self::TheoryLab => 'Theory + Lab',
        };
    }
}
