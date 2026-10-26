<?php

namespace App\Concerns;

use App\Models\Program;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Roll number, batch and program filters shared by the admin and
 * super admin applications and students lists. The using component
 * must have a $department_id property and an isSuperAdmin() method.
 */
trait FiltersByStudentProfile
{
    #[Url]
    public string $roll_no = '';

    #[Url]
    public string $batch = '';

    #[Url]
    public string $program_id = '';

    /**
     * The program list narrows to the chosen department, so a program
     * picked under another department no longer applies.
     */
    public function updatedDepartmentId(): void
    {
        $this->program_id = '';
    }

    /**
     * @return list<string>
     */
    protected function studentProfileFilterProperties(): array
    {
        return ['roll_no', 'batch', 'program_id'];
    }

    protected function hasStudentProfileFilters(): bool
    {
        return $this->roll_no !== '' || $this->batch !== '' || $this->program_id !== '';
    }

    /**
     * @param  Builder<Student>  $query
     */
    protected function applyStudentProfileFilters(Builder $query): void
    {
        $query
            ->when($this->roll_no !== '', fn (Builder $query) => $query->where('registration_no', 'like', '%'.trim($this->roll_no).'%'))
            ->when($this->batch !== '', fn (Builder $query) => $query->where('batch', $this->batch))
            ->when($this->program_id !== '', fn (Builder $query) => $query->where('program_id', $this->program_id));
    }

    /**
     * Batch codes present among the visible students, newest first
     * (F23, S23, F22, ...), keyed by code with a readable label.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function batches(): array
    {
        $batches = Student::query()
            ->when($this->isSuperAdmin() && $this->department_id !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('department_id', $this->department_id)))
            ->distinct()
            ->pluck('batch')
            ->sortByDesc(fn (string $batch) => substr($batch, 1).($batch[0] === 'F' ? '1' : '0'))
            ->values()
            ->all();

        return array_combine($batches, array_map(Student::labelForBatch(...), $batches));
    }

    /**
     * @return Collection<int, Program>
     */
    #[Computed]
    public function programs(): Collection
    {
        return Program::query()
            ->when($this->isSuperAdmin() && $this->department_id !== '', fn (Builder $query) => $query->where('department_id', $this->department_id))
            ->orderBy('name')
            ->get();
    }
}
