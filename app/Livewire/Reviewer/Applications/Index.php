<?php

namespace App\Livewire\Reviewer\Applications;

use App\Concerns\FiltersByStudentProfile;
use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\Program;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Forwarded to me" for an HOD or Dean: only applications forwarded to
 * them (Application::forwardedTo), with status and student filters.
 */
#[Title('Forwarded to me')]
class Index extends Component
{
    use FiltersByStudentProfile, WithPagination;

    #[Url]
    public string $status = '';

    /**
     * Required by FiltersByStudentProfile; reviewers have no department picker.
     */
    public string $department_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Application::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', ...$this->studentProfileFilterProperties()], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['status', ...$this->studentProfileFilterProperties()]);
        $this->resetPage();
    }

    public function isSuperAdmin(): bool
    {
        return false;
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->status !== '' || $this->hasStudentProfileFilters();
    }

    /**
     * @return LengthAwarePaginator<int, Application>
     */
    #[Computed]
    public function applications(): LengthAwarePaginator
    {
        $role = $this->role();

        return Application::query()
            ->forwardedTo(Auth::user())
            ->with([
                'category',
                'student.user',
                'student.program',
                'forwards' => fn ($query) => $query->where('to_role', $role)->latest('id'),
                'remarks' => fn ($query) => $query->where('author_role', $role)->latest('id'),
            ])
            ->withMax(['forwards as forwarded_at' => fn (Builder $query) => $query->where('to_role', $role)], 'created_at')
            ->when($this->status !== '', fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->hasStudentProfileFilters(), fn (Builder $query) => $query->whereHas('student', fn (Builder $query) => $this->applyStudentProfileFilters($query)))
            ->orderByDesc('forwarded_at')
            ->paginate(15);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function statuses(): array
    {
        return array_combine(
            array_map(fn (ApplicationStatus $status) => $status->value, ApplicationStatus::cases()),
            array_map(fn (ApplicationStatus $status) => Str::headline($status->value), ApplicationStatus::cases()),
        );
    }

    /**
     * Programs of the HOD's department, or of every department in the
     * Dean's faculty.
     *
     * @return Collection<int, Program>
     */
    #[Computed]
    public function programs(): Collection
    {
        $user = Auth::user();

        return Program::query()
            ->when(
                $user->hasRole(RoleName::Dean->value),
                fn (Builder $query) => $query->whereHas('department', fn (Builder $query) => $query->where('faculty_id', $user->faculty_id)),
                fn (Builder $query) => $query->where('department_id', $user->department_id),
            )
            ->orderBy('name')
            ->get();
    }

    /**
     * Batches of the students whose applications were forwarded here.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function batches(): array
    {
        $batches = Student::query()
            ->whereHas('applications', fn (Builder $query) => $query->forwardedTo(Auth::user()))
            ->distinct()
            ->pluck('batch')
            ->sortByDesc(fn (string $batch) => substr($batch, 1).($batch[0] === 'F' ? '1' : '0'))
            ->values()
            ->all();

        return array_combine($batches, array_map(Student::labelForBatch(...), $batches));
    }

    public function routePrefix(): string
    {
        return $this->role().'.';
    }

    private function role(): string
    {
        return Auth::user()->hasRole(RoleName::Dean->value) ? RoleName::Dean->value : RoleName::Hod->value;
    }

    public function render(): View
    {
        return view('livewire.reviewer.applications.index');
    }
}
