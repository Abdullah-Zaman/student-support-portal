<?php

namespace App\Livewire\Student;

use App\Models\Student;
use App\Models\TimetableVersion;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('My Profile')]
class Profile extends Component
{
    public ?int $current_semester = null;

    public string $section = '';

    /**
     * Show every section of the student's program, not only those of
     * their current semester (for students repeating or ahead a course).
     */
    public bool $showAllSections = false;

    public function mount(): void
    {
        $student = $this->student();

        $this->current_semester = $student->current_semester;
        $this->section = (string) $student->section;
        $this->showAllSections = $this->section !== ''
            && ! in_array($this->section, $this->currentSemesterSections(), true);
    }

    public function updateSemester(): void
    {
        abort_if($this->student()->is_alumni, 403);

        $validated = $this->validate([
            'current_semester' => ['required', 'integer', 'between:1,8'],
        ]);

        $this->student()->update($validated);

        unset($this->sectionOptions);

        Flux::toast(variant: 'success', text: __('Semester updated.'));
    }

    public function updateSection(): void
    {
        $validated = $this->validate([
            'section' => ['nullable', 'string', Rule::in($this->programSections())],
        ], [
            'section.in' => __('Choose a section from the published timetable.'),
        ]);

        $this->student()->update(['section' => $validated['section'] ?: null]);

        Flux::toast(variant: 'success', text: __('Section updated.'));
    }

    #[Computed]
    public function timetable(): ?TimetableVersion
    {
        return TimetableVersion::publishedForDepartment(Auth::user()->department_id);
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function sectionOptions(): array
    {
        return $this->showAllSections ? $this->programSections() : $this->currentSemesterSections();
    }

    /**
     * @return list<string>
     */
    private function programSections(): array
    {
        return $this->timetable?->sectionNames($this->student()->program->code) ?? [];
    }

    /**
     * @return list<string>
     */
    private function currentSemesterSections(): array
    {
        return $this->timetable?->sectionNames($this->student()->program->code, $this->student()->current_semester) ?? [];
    }

    private function student(): Student
    {
        return Auth::user()->student;
    }
}
