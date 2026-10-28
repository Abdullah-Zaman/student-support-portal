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
    public const TIMEZONE = 'Asia/Karachi';

    public const STATE_NO_SECTION = 'no_section';

    public const STATE_SECTION_MISSING = 'section_missing';

    public const STATE_NO_CLASSES_TODAY = 'no_classes_today';

    public const STATE_HAS_CLASSES_TODAY = 'has_classes_today';

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
     * What the dashboard's "Today" card shows: no section chosen yet, a
     * chosen section the published timetable does not have, a chosen
     * section with no classes today, or classes today.
     */
    public function todayState(?CarbonInterface $now = null): string
    {
        return match (true) {
            $this->section === null => self::STATE_NO_SECTION,
            $this->sectionIsMissing() => self::STATE_SECTION_MISSING,
            $this->today($now)['classes']->isEmpty() => self::STATE_NO_CLASSES_TODAY,
            default => self::STATE_HAS_CLASSES_TODAY,
        };
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
        $now ??= now(self::TIMEZONE);
        $classes = $this->classesOn($now->dayOfWeekIso);

        $current = $classes->first(fn (TimetableClass $class): bool => $class->startsOn($now)->lessThanOrEqualTo($now) && $class->endsOn($now)->greaterThan($now));
        $next = $classes->first(fn (TimetableClass $class): bool => $class->startsOn($now)->greaterThan($now));

        return ['classes' => $classes, 'current' => $current, 'next' => $next];
    }
}
