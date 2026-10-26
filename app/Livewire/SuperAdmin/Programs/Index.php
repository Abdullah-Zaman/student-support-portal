<?php

namespace App\Livewire\SuperAdmin\Programs;

use App\Models\Department;
use App\Models\Program;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Programs')]
class Index extends Component
{
    public string $filter_department_id = '';

    public ?int $editing_id = null;

    public string $department_id = '';

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Program::class);
    }

    /**
     * @return Collection<int, Program>
     */
    #[Computed]
    public function programs(): Collection
    {
        return Program::query()
            ->with('department')
            ->withCount('students')
            ->when($this->filter_department_id !== '', fn (Builder $query) => $query->where('department_id', $this->filter_department_id))
            ->orderBy('code')
            ->get();
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
     * A code already used in students' registration numbers can't change,
     * or those registration numbers would stop matching their program.
     */
    #[Computed]
    public function isCodeLocked(): bool
    {
        return $this->editing_id !== null
            && Program::query()->whereKey($this->editing_id)->whereHas('students')->exists();
    }

    public function startCreate(): void
    {
        $this->authorize('create', Program::class);

        $this->reset(['editing_id', 'department_id', 'code', 'name', 'is_active']);
        $this->department_id = $this->filter_department_id !== '' ? $this->filter_department_id : (string) ($this->departments()->first()->id ?? '');
        $this->resetValidation();

        $this->modal('program-form')->show();
    }

    public function startEdit(int $id): void
    {
        $program = Program::query()->findOrFail($id);
        $this->authorize('update', $program);

        $this->editing_id = $program->id;
        $this->department_id = (string) $program->department_id;
        $this->code = $program->code;
        $this->name = $program->name;
        $this->is_active = $program->is_active;
        $this->resetValidation();

        $this->modal('program-form')->show();
    }

    public function save(): void
    {
        $program = $this->editing_id ? Program::query()->findOrFail($this->editing_id) : null;

        $this->authorize($program ? 'update' : 'create', $program ?? Program::class);

        $this->code = strtoupper(trim($this->code));

        $validated = $this->validate([
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'code' => ['required', 'string', 'between:2,10', 'regex:/^[A-Z]+$/', Rule::unique(Program::class, 'code')->ignore($program?->id)],
            'name' => ['required', 'string', 'max:100', Rule::unique(Program::class, 'name')->ignore($program?->id)],
            'is_active' => ['boolean'],
        ], [
            'code.unique' => __('A program with this code already exists.'),
            'code.regex' => __('Use capital letters only, as in the registration number (e.g. BSSE).'),
            'name.unique' => __('A program with this name already exists.'),
        ]);

        if ($program !== null && $validated['code'] !== $program->code && $this->isCodeLocked()) {
            $this->addError('code', __('Students are already registered with this code, so it cannot be changed.'));

            return;
        }

        if ($program === null) {
            Program::create($validated);
        } else {
            $program->update($validated);
        }

        unset($this->programs);
        $this->modal('program-form')->close();
        Flux::toast(variant: 'success', text: $program ? __('Program updated.') : __('Program created.'));
    }

    /**
     * Deactivating only closes new registrations for the program;
     * existing students and their applications are untouched.
     */
    public function toggleActive(int $id): void
    {
        $program = Program::query()->findOrFail($id);
        $this->authorize('update', $program);

        $program->update(['is_active' => ! $program->is_active]);

        unset($this->programs);
        Flux::toast(variant: 'success', text: $program->is_active ? __('Program activated.') : __('Program deactivated.'));
    }

    public function render(): View
    {
        return view('livewire.super-admin.programs.index');
    }
}
