<?php

namespace App\Support\Timetable;

use App\Models\Student;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * A student's own classes from their department's published timetable,
 * filtered to the section they picked in their profile.
 */
final readonly class StudentSchedule
{
    /**
     * @param  Collection<int, TimetableClass>  $classes
     */
    private function __construct(
        public ?TimetableVersion $version,
        public ?string $section,
        public Collection $classes,
    ) {}

    public static function for(Student $student, ?int $departmentId): self
    {
        $version = TimetableVersion::publishedForDepartment($departmentId);
        $section = $student->section;

        $classes = $version !== null && $section !== null
            ? $version->classes()->forSection($section)->inWeekOrder()->get()
            : new Collection;

        return new self($version, $section, $classes);
    }

    /**
     * The student picked a section, but the published timetable has no
     * classes for it (e.g. a new version renamed the sections).
     */
    public function sectionIsMissing(): bool
    {
        return $this->version !== null && $this->section !== null && $this->classes->isEmpty();
    }

    /**
     * @return Collection<int, TimetableClass>
     */
    public function classesOn(int $day): Collection
    {
        return $this->classes->where('day', $day)->values();
    }

    /**
     * Days that have at least one class, Monday first; Monday to Friday
     * are always shown so the week reads as a week.
     *
     * @return list<int>
     */
    public function visibleDays(): array
    {
        $days = array_unique([1, 2, 3, 4, 5, ...$this->classes->pluck('day')->all()]);
        sort($days);

        return $days;
    }

    /**
     * Today's classes and where "now" falls among them.
     *
     * @return array{classes: Collection<int, TimetableClass>, current: TimetableClass|null, next: TimetableClass|null}
     */
    public function today(?CarbonInterface $now = null): array
    {
        $now ??= now();
        $classes = $this->classesOn($now->dayOfWeekIso);

        $current = $classes->first(fn (TimetableClass $class): bool => $class->startsOn($now)->lessThanOrEqualTo($now) && $class->endsOn($now)->greaterThan($now));
        $next = $classes->first(fn (TimetableClass $class): bool => $class->startsOn($now)->greaterThan($now));

        return ['classes' => $classes, 'current' => $current, 'next' => $next];
    }
}
