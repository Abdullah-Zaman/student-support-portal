<?php

namespace App\Livewire\Admin\Timetables;

use App\Enums\RoleName;
use App\Models\Student;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;
use App\Support\Timetable\TimetableExplorer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Read-only statistics and search over the department's published
 * timetable. It never writes; every request re-checks the policy.
 */
class Explorer extends Component
{
    public const MODES = ['teacher', 'subject', 'room', 'section', 'student'];

    /** Most matches shown at once; narrow the search to see others. */
    public const RESULT_LIMIT = 10;

    /**
     * Super Admin only: the department chosen on the page. An Admin
     * Officer's own department always wins over this value.
     */
    #[Reactive]
    public string $departmentId = '';

    public string $mode = 'teacher';

    public string $term = '';

    public string $day = '';

    public function mount(): void
    {
        $this->authorize('viewAny', TimetableVersion::class);
    }

    public function updatedMode(): void
    {
        $this->authorizeExplore();

        if (! in_array($this->mode, self::MODES, true)) {
            $this->mode = 'teacher';
        }
    }

    public function updatedTerm(): void
    {
        $this->authorizeExplore();
    }

    public function updatedDay(): void
    {
        $this->authorizeExplore();
    }

    /**
     * The department the lookups are limited to, after the policy check.
     */
    private function authorizeExplore(): ?int
    {
        $user = Auth::user();
        $departmentId = $user->hasRole(RoleName::SuperAdmin->value)
            ? ($this->departmentId !== '' ? (int) $this->departmentId : null)
            : $user->department_id;

        $this->authorize('explore', [TimetableVersion::class, $departmentId ?? $user->department_id]);

        return $departmentId;
    }

    #[Computed]
    public function version(): ?TimetableVersion
    {
        return TimetableVersion::publishedForDepartment($this->authorizeExplore());
    }

    #[Computed]
    public function explorer(): ?TimetableExplorer
    {
        if ($this->version() === null) {
            return null;
        }

        /** @var Collection<int, TimetableClass> $classes */
        $classes = $this->version()->classes()->with('sections:id,timetable_class_id,section')->get();

        return new TimetableExplorer($classes);
    }

    /**
     * @return array{teachers: int, rooms: int, sections: int, subjects: int, classes: int}|null
     */
    #[Computed]
    public function stats(): ?array
    {
        return $this->explorer()?->stats();
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function days(): array
    {
        return $this->explorer()?->days() ?? [];
    }

    private function dayFilter(): ?int
    {
        $day = (int) $this->day;

        return in_array($day, $this->days(), true) ? $day : null;
    }

    /**
     * The matches for the current mode, capped at RESULT_LIMIT.
     *
     * @return array{items: Collection<int, array<string, mixed>>, total: int, student: array{name: string, semester: string, registration_no: string, section: string|null}|null, notFound: bool}|null
     */
    #[Computed]
    public function results(): ?array
    {
        $departmentId = $this->authorizeExplore();
        $term = TimetableExplorer::normalize($this->term);

        if ($this->explorer() === null || $term === '') {
            return null;
        }

        $day = $this->dayFilter();
        $student = null;

        if ($this->mode === 'student') {
            $record = Student::query()
                ->with('user:id,name,department_id')
                ->whereHas('user', fn ($query) => $query->where('department_id', $departmentId))
                ->whereRaw('lower(registration_no) = ?', [mb_strtolower(trim($this->term))])
                ->first();

            if ($record === null) {
                return ['items' => collect(), 'total' => 0, 'student' => null, 'notFound' => true];
            }

            $student = [
                'name' => $record->user->name,
                'semester' => $record->semesterLabel(),
                'registration_no' => $record->registration_no,
                'section' => $record->section,
            ];
            $matches = $record->section === null
                ? collect()
                : $this->explorer()->sections($record->section, $day, exact: true);
        } else {
            $matches = match ($this->mode) {
                'subject' => $this->explorer()->subjects($term, $day),
                'room' => $this->explorer()->rooms($term, $day),
                'section' => $this->explorer()->sections($term, $day),
                default => $this->explorer()->teachers($term, $day),
            };
        }

        return [
            'items' => $matches->take(self::RESULT_LIMIT),
            'total' => $matches->count(),
            'student' => $student,
            'notFound' => false,
        ];
    }

    public function render(): View
    {
        $this->authorizeExplore();

        return view('livewire.admin.timetables.explorer');
    }
}
