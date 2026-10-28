<?php

namespace App\Livewire\Admin\Students;

use App\Concerns\FiltersByStudentProfile;
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
    use FiltersByStudentProfile, WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $semester = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $department_id = '';

    #[Url]
    public string $test = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Student::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'semester', 'status', 'department_id', 'test', ...$this->studentProfileFilterProperties()], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'semester', 'status', 'department_id', 'test', ...$this->studentProfileFilterProperties()]);
        $this->resetPage();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->hasStudentProfileFilters()
            || $this->semester !== ''
            || $this->status !== ''
            || $this->department_id !== ''
            || $this->test !== '';
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
            ->with(['user.department', 'program'])
            ->when($this->search !== '', function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('registration_no', 'like', '%'.$this->search.'%')
                        ->orWhereHas('user', function (Builder $query) {
                            $query->where('name', 'like', '%'.$this->search.'%')
                                ->orWhere('email', 'like', '%'.$this->search.'%');
                        });
                });
            })
            ->tap(fn (Builder $query) => $this->applyStudentProfileFilters($query))
            ->when($this->semester !== '', fn (Builder $query) => $query->where('current_semester', $this->semester))
            ->when($this->status !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('status', $this->status)))
            ->when($this->isSuperAdmin() && $this->department_id !== '', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('department_id', $this->department_id)))
            ->when($this->test === 'only', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('is_test', true)))
            ->when($this->test === 'hide', fn (Builder $query) => $query->whereHas('user', fn (Builder $query) => $query->where('is_test', false)))
            ->orderBy('registration_no')
            ->paginate(15);
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
