<?php

namespace App\Livewire\Admin\Students;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Department;
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

#[Title('Students')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $program = '';

    #[Url]
    public string $semester = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $department_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'program', 'semester', 'status', 'department_id'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'program', 'semester', 'status', 'department_id']);
        $this->resetPage();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->program !== ''
            || $this->semester !== ''
            || $this->status !== ''
            || $this->department_id !== '';
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return Auth::user()->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    /**
     * @return LengthAwarePaginator<int, Student>
     */
    #[Computed]
    public function students(): LengthAwarePaginator
    {
        return Student::query()
            ->with('user.department')
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('registration_no', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', function (Builder $query) {
                            $query->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('email', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->when($this->program !== '', fn (Builder $query) => $query->where('program', $this->program))
            ->when($this->semester !== '', fn (Builder $query) => $query->where('current_semester', $this->semester))
            ->when($this->status !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('status', $this->status)))
            ->when($this->isSuperAdmin() && $this->department_id !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('department_id', $this->department_id)))
            ->orderBy('registration_no')
            ->paginate(15);
    }

    /**
     * Keyed by the program's full label, since that's what's actually
     * stored on students.program (see CreateNewUser), not the short code.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function programs(): array
    {
        $labels = array_values(config('students.programs'));

        return array_combine($labels, $labels);
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function statuses(): array
    {
        return array_combine(
            array_map(fn (UserStatus $status) => $status->value, UserStatus::cases()),
            array_map(fn (UserStatus $status) => Str::headline($status->value), UserStatus::cases()),
        );
    }

    public function render(): View
    {
        return view('livewire.admin.students.index');
    }
}
