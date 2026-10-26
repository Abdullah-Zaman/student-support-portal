<?php

namespace App\Livewire\SuperAdmin\Departments;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\ApplicationCategory;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Departments')]
class Index extends Component
{
    public string $filter_faculty_id = '';

    public ?int $editing_id = null;

    public string $faculty_id = '';

    public string $name = '';

    public string $code = '';

    public bool $is_active = true;

    /**
     * Shown after a department is activated without everything it needs
     * to actually handle applications.
     */
    public ?string $activation_warning = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Department::class);
    }

    /**
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()
            ->with('faculty')
            ->withCount('programs')
            ->when($this->filter_faculty_id !== '', fn (Builder $query) => $query->where('faculty_id', $this->filter_faculty_id))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return Collection<int, Faculty>
     */
    #[Computed]
    public function faculties(): Collection
    {
        return Faculty::query()->orderBy('name')->get();
    }

    public function startCreate(): void
    {
        $this->authorize('create', Department::class);

        $this->reset(['editing_id', 'faculty_id', 'name', 'code', 'is_active']);
        $this->faculty_id = $this->filter_faculty_id !== '' ? $this->filter_faculty_id : (string) ($this->faculties()->first()->id ?? '');
        $this->resetValidation();

        $this->modal('department-form')->show();
    }

    public function startEdit(int $id): void
    {
        $department = Department::query()->findOrFail($id);
        $this->authorize('update', $department);

        $this->editing_id = $department->id;
        $this->faculty_id = (string) $department->faculty_id;
        $this->name = $department->name;
        $this->code = $department->code;
        $this->is_active = $department->is_active;
        $this->resetValidation();

        $this->modal('department-form')->show();
    }

    public function save(): void
    {
        $department = $this->editing_id ? Department::query()->findOrFail($this->editing_id) : null;

        $this->authorize($department ? 'update' : 'create', $department ?? Department::class);

        $this->code = strtoupper(trim($this->code));

        $validated = $this->validate([
            'faculty_id' => ['required', 'integer', Rule::exists(Faculty::class, 'id')],
            'name' => ['required', 'string', 'max:100', Rule::unique(Department::class, 'name')->ignore($department?->id)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/', Rule::unique(Department::class, 'code')->ignore($department?->id)],
            'is_active' => ['boolean'],
        ], [
            'name.unique' => __('A department with this name already exists.'),
            'code.unique' => __('A department with this code already exists.'),
            'code.regex' => __('Use capital letters and digits only.'),
        ]);

        $wasActive = $department !== null && $department->is_active;

        if ($department === null) {
            $department = Department::create($validated);
            $toast = __('Department created.');
        } else {
            $department->update($validated);
            $toast = __('Department updated.');
        }

        $this->warnIfActivatedIncomplete($department, $wasActive);

        unset($this->departments);
        $this->modal('department-form')->close();
        Flux::toast(variant: 'success', text: $toast);
    }

    /**
     * Deactivating only closes new registrations for the department's
     * programs; existing students and applications are untouched.
     */
    public function toggleActive(int $id): void
    {
        $department = Department::query()->findOrFail($id);
        $this->authorize('update', $department);

        $wasActive = $department->is_active;
        $department->update(['is_active' => ! $wasActive]);

        $this->warnIfActivatedIncomplete($department, $wasActive);

        unset($this->departments);
        Flux::toast(variant: 'success', text: $department->is_active ? __('Department activated.') : __('Department deactivated.'));
    }

    public function dismissActivationWarning(): void
    {
        $this->activation_warning = null;
    }

    private function warnIfActivatedIncomplete(Department $department, bool $wasActive): void
    {
        $this->activation_warning = null;

        if ($wasActive || ! $department->is_active) {
            return;
        }

        $missing = [];

        $hasActiveAdminOfficer = User::query()
            ->role(RoleName::AdminOfficer->value)
            ->where('department_id', $department->id)
            ->where('status', UserStatus::Active)
            ->exists();

        if (! $hasActiveAdminOfficer) {
            $missing[] = __('an active Admin Officer');
        }

        $hasActiveCategories = ApplicationCategory::query()
            ->where('department_id', $department->id)
            ->where('is_active', true)
            ->exists();

        if (! $hasActiveCategories) {
            $missing[] = __('active application categories');
        }

        if ($missing !== []) {
            $this->activation_warning = __(':department is active but has no :missing yet. Its students cannot get their applications handled until this is set up.', [
                'department' => $department->name,
                'missing' => implode(__(' and no '), $missing),
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.super-admin.departments.index');
    }
}
