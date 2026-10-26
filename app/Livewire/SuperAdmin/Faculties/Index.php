<?php

namespace App\Livewire\SuperAdmin\Faculties;

use App\Models\Faculty;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Faculties')]
class Index extends Component
{
    public ?int $editing_id = null;

    public string $name = '';

    public string $code = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', Faculty::class);
    }

    /**
     * @return Collection<int, Faculty>
     */
    #[Computed]
    public function faculties(): Collection
    {
        return Faculty::query()->withCount('departments')->orderBy('name')->get();
    }

    public function startCreate(): void
    {
        $this->authorize('create', Faculty::class);

        $this->reset(['editing_id', 'name', 'code', 'is_active']);
        $this->resetValidation();

        $this->modal('faculty-form')->show();
    }

    public function startEdit(int $id): void
    {
        $faculty = Faculty::query()->findOrFail($id);
        $this->authorize('update', $faculty);

        $this->editing_id = $faculty->id;
        $this->name = $faculty->name;
        $this->code = $faculty->code;
        $this->is_active = $faculty->is_active;
        $this->resetValidation();

        $this->modal('faculty-form')->show();
    }

    public function save(): void
    {
        $faculty = $this->editing_id ? Faculty::query()->findOrFail($this->editing_id) : null;

        $this->authorize($faculty ? 'update' : 'create', $faculty ?? Faculty::class);

        $this->code = strtoupper(trim($this->code));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique(Faculty::class, 'name')->ignore($faculty?->id)],
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9]+$/', Rule::unique(Faculty::class, 'code')->ignore($faculty?->id)],
            'is_active' => ['boolean'],
        ], [
            'name.unique' => __('A faculty with this name already exists.'),
            'code.unique' => __('A faculty with this code already exists.'),
            'code.regex' => __('Use capital letters and digits only.'),
        ]);

        if ($faculty === null) {
            Faculty::create($validated);
        } else {
            $faculty->update($validated);
        }

        unset($this->faculties);
        $this->modal('faculty-form')->close();
        Flux::toast(variant: 'success', text: $faculty ? __('Faculty updated.') : __('Faculty created.'));
    }

    /**
     * Deactivating only closes new registrations for the faculty's
     * programs; existing students and applications are untouched.
     */
    public function toggleActive(int $id): void
    {
        $faculty = Faculty::query()->findOrFail($id);
        $this->authorize('update', $faculty);

        $faculty->update(['is_active' => ! $faculty->is_active]);

        unset($this->faculties);
        Flux::toast(variant: 'success', text: $faculty->is_active ? __('Faculty activated.') : __('Faculty deactivated.'));
    }

    public function render(): View
    {
        return view('livewire.super-admin.faculties.index');
    }
}
