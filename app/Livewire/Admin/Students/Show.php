<?php

namespace App\Livewire\Admin\Students;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Application;
use App\Models\Program;
use App\Models\Student;
use App\Support\RegistrationNumber;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Student Profile')]
class Show extends Component
{
    public Student $student;

    public string $name = '';

    public string $program_id = '';

    public int $current_semester = 1;

    public string $batch = '';

    public function mount(Student $student): void
    {
        $this->authorize('view', $student);

        $this->student = $student->load(['user', 'program']);
        $this->name = $student->user->name;
        $this->program_id = (string) $student->program_id;
        $this->current_semester = $student->current_semester;
        $this->batch = $student->batch;
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return Auth::user()->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * Every program of the student's department, inactive ones included,
     * since an existing student may belong to a since-deactivated program.
     *
     * @return Collection<int, Program>
     */
    #[Computed]
    public function programs(): Collection
    {
        return Program::query()
            ->where('department_id', $this->student->user->department_id)
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Application>
     */
    #[Computed]
    public function applications(): Collection
    {
        return Application::query()
            ->where('student_id', $this->student->id)
            ->with('category')
            ->latest()
            ->get();
    }

    #[Computed]
    public function isSuspended(): bool
    {
        return $this->student->user->status === UserStatus::Suspended;
    }

    public function updateName(): void
    {
        $this->authorize('updateName', $this->student);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
        ])['name'];

        $this->student->user->update(['name' => $validated]);

        Flux::toast(variant: 'success', text: __('Name updated.'));
    }

    /**
     * Program/semester/batch edit — Super Admin only. Changing
     * current_semester never touches semester_at_submission on any
     * existing application, since that is a point-in-time snapshot.
     */
    public function updateProfile(): void
    {
        $this->authorize('updateProfile', $this->student);

        $programs = $this->programs();

        $validated = $this->validate([
            'program_id' => ['required', 'integer', Rule::in($programs->modelKeys())],
            'current_semester' => ['required', 'integer', 'between:1,8'],
            'batch' => ['required', 'string', 'max:20'],
        ]);

        $program = $programs->firstWhere('id', (int) $validated['program_id']);
        $parsed = RegistrationNumber::parse($this->student->registration_no, openProgramsOnly: false);

        if (($parsed['program_code'] ?? null) !== $program?->code) {
            $this->addError('program_id', __("This doesn't match the student's registration number."));

            return;
        }

        $this->student->update([
            'program_id' => $program->id,
            'current_semester' => $validated['current_semester'],
            'batch' => $validated['batch'],
        ]);

        Flux::toast(variant: 'success', text: __('Student profile updated.'));
    }

    public function suspend(): void
    {
        $this->authorize('suspend', $this->student);

        $this->student->user->update(['status' => UserStatus::Suspended]);

        Flux::toast(variant: 'success', text: __('Student suspended.'));
    }

    public function reactivate(): void
    {
        $this->authorize('reactivate', $this->student);

        $this->student->user->update(['status' => UserStatus::Active]);

        Flux::toast(variant: 'success', text: __('Student reactivated.'));
    }

    public function render(): View
    {
        return view('livewire.admin.students.show');
    }
}
