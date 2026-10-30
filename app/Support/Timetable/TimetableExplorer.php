<?php

namespace App\Support\Timetable;

use App\Models\TimetableClass;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Read-only lookups over the classes of one timetable version. A version
 * holds a few hundred classes, so they are loaded once and searched in
 * memory; matching is trimmed, case-insensitive and ignores repeated spaces.
 */
final class TimetableExplorer
{
    /**
     * @param  Collection<int, TimetableClass>  $classes  with `sections` loaded
     */
    public function __construct(private readonly Collection $classes) {}

    public static function normalize(?string $text): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', (string) $text)));
    }

    /**
     * Section comparison key: lowercase with spaces, dashes and underscores
     * removed, so "7-B", "bsse 7b" and "BSSE-7B" line up.
     */
    public static function sectionKey(?string $text): string
    {
        return (string) preg_replace('/[\s\-_]+/u', '', mb_strtolower((string) $text));
    }

    /**
     * @return array{teachers: int, rooms: int, sections: int, subjects: int, classes: int}
     */
    public function stats(): array
    {
        return [
            'teachers' => $this->classes->map(fn (TimetableClass $class): string => self::normalize($class->teacher))->filter()->unique()->count(),
            'rooms' => $this->classes->map(fn (TimetableClass $class): string => self::normalize($class->room))->filter()->unique()->count(),
            'sections' => $this->classes->flatMap(fn (TimetableClass $class) => $class->sections->pluck('section'))->unique()->count(),
            'subjects' => $this->classes->map(fn (TimetableClass $class): string => self::normalize($class->baseCourseName()))->filter()->unique()->count(),
            'classes' => $this->classes->count(),
        ];
    }

    /**
     * Days that have at least one class anywhere in the timetable.
     *
     * @return list<int>
     */
    public function days(): array
    {
        return array_values($this->classes->pluck('day')->unique()->sort()->all());
    }

    /**
     * Per matching teacher: the week by day plus the workload summary.
     *
     * @return Collection<int, array{name: string, byDay: Collection<array-key, Collection<int, TimetableClass>>, perDay: array<int>, teachingDays: int, freeDays: list<int>, total: int}>
     */
    public function teachers(string $term, ?int $day): Collection
    {
        $needle = self::normalize($term);
        $allDays = $this->days();

        return $this->classes
            ->filter(fn (TimetableClass $class): bool => $class->teacher !== null && str_contains(self::normalize($class->teacher), $needle))
            ->groupBy(fn (TimetableClass $class): string => self::normalize($class->teacher))
            ->sortKeys()
            ->map(function (Collection $teacherClasses) use ($day, $allDays): array {
                $week = $this->sorted($teacherClasses);
                $byDay = $week->groupBy('day');
                $perDay = $byDay->map->count()->all();

                return [
                    'name' => self::tidy($week->first()->teacher),
                    'byDay' => $day === null ? $byDay : $byDay->only([$day]),
                    'perDay' => $perDay,
                    'teachingDays' => count($perDay),
                    'freeDays' => array_values(array_diff($allDays, array_keys($perDay))),
                    'total' => $week->count(),
                ];
            })
            ->values();
    }

    /**
     * Per matching subject (theory and lab together): who teaches it, when, where.
     *
     * @return Collection<int, array{name: string, classes: Collection<int, TimetableClass>}>
     */
    public function subjects(string $term, ?int $day): Collection
    {
        $needle = self::normalize($term);

        return $this->sorted($this->onDay($day)->filter(fn (TimetableClass $class): bool => str_contains(self::normalize($class->course), $needle)))
            ->groupBy(fn (TimetableClass $class): string => self::normalize($class->baseCourseName()))
            ->sortKeys()
            ->map(fn (Collection $subjectClasses): array => ['name' => $subjectClasses->first()->baseCourseName(), 'classes' => $subjectClasses])
            ->values();
    }

    /**
     * Per matching room: booked classes and free slots for each day.
     *
     * @return Collection<int, array{name: string, days: array<int, array{booked: Collection<int, TimetableClass>, free: list<string>}>}>
     */
    public function rooms(string $term, ?int $day): Collection
    {
        $needle = self::normalize($term);
        $slots = $this->atomicSlots();
        $days = $day === null ? $this->days() : [$day];

        return $this->classes
            ->filter(fn (TimetableClass $class): bool => str_contains(self::normalize($class->room), $needle))
            ->groupBy(fn (TimetableClass $class): string => self::normalize($class->room))
            ->sortKeys()
            ->map(function (Collection $roomClasses) use ($days, $slots): array {
                $perDay = [];

                foreach ($days as $dayNumber) {
                    $booked = $this->sorted($roomClasses->where('day', $dayNumber));
                    $free = array_values(array_filter($slots, fn (array $slot): bool => $booked->doesntContain(fn (TimetableClass $class): bool => $slot[0] < self::hm($class->ends_at) && self::hm($class->starts_at) < $slot[1])));
                    $perDay[$dayNumber] = ['booked' => $booked, 'free' => array_map(fn (array $slot): string => self::twelveHour($slot[0]).' – '.self::twelveHour($slot[1]), $free)];
                }

                return ['name' => self::tidy($roomClasses->first()->room), 'days' => $perDay];
            })
            ->values();
    }

    /**
     * Per matching section: its full weekly timetable.
     *
     * @return Collection<int, array{name: string, classes: Collection<int, TimetableClass>}>
     */
    public function sections(string $term, ?int $day, bool $exact = false): Collection
    {
        $needle = self::sectionKey($term);

        return $this->classes
            ->flatMap(fn (TimetableClass $class) => $class->sections->pluck('section'))
            ->unique()
            ->filter(fn (string $section): bool => $exact ? self::sectionKey($section) === $needle : str_contains(self::sectionKey($section), $needle))
            ->sort()
            ->values()
            ->map(fn (string $name): array => [
                'name' => $name,
                'classes' => $this->sorted($this->onDay($day)->filter(fn (TimetableClass $class): bool => $class->sections->contains('section', $name))),
            ]);
    }

    /**
     * The distinct time slots of the timetable as [start, end] pairs,
     * leaving out merged slots that span shorter ones.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function atomicSlots(): array
    {
        $slots = $this->classes
            ->map(fn (TimetableClass $class): array => [self::hm($class->starts_at), self::hm($class->ends_at)])
            ->unique(fn (array $slot): string => $slot[0].$slot[1])
            ->sortBy(fn (array $slot): string => $slot[0].$slot[1])
            ->values();

        return array_values($slots
            ->reject(fn (array $slot): bool => $slots->contains(fn (array $other): bool => $other !== $slot && $other[0] >= $slot[0] && $other[1] <= $slot[1]))
            ->values()
            ->all());
    }

    /**
     * @return Collection<int, TimetableClass>
     */
    private function onDay(?int $day): Collection
    {
        return $day === null ? $this->classes : $this->classes->where('day', $day);
    }

    /**
     * @param  Collection<int, TimetableClass>  $classes
     * @return Collection<int, TimetableClass>
     */
    private function sorted(Collection $classes): Collection
    {
        return $classes->sortBy(fn (TimetableClass $class): string => $class->day.$class->starts_at.$class->room)->values();
    }

    private static function hm(string $time): string
    {
        return substr($time, 0, 5);
    }

    private static function twelveHour(string $time): string
    {
        return Carbon::createFromFormat('H:i', $time)->format('g:i A');
    }

    private static function tidy(?string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) $text));
    }
}
