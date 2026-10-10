<?php

namespace App\Livewire\SuperAdmin\Categories;

use App\Models\ApplicationCategory;
use App\Models\Department;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Categories')]
class Index extends Component
{
    public string $department_id = '';

    public ?int $editing_id = null;

    public string $name = '';

    public bool $is_active = true;

    public function mount(): void
    {
        $this->authorize('viewAny', ApplicationCategory::class);

        $this->department_id = (string) (Department::query()->orderBy('name')->value('id') ?? '');
    }

    public function updatedDepartmentId(): void
    {
        unset($this->categories);
    }

    /**
     * @return Collection<int, ApplicationCategory>
     */
    #[Computed]
    public function categories(): Collection
    {
        return ApplicationCategory::query()
            ->where('department_id', $this->department_id === '' ? 0 : $this->department_id)
            ->orderBy('sort_order')
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

    public function startCreate(): void
    {
        $this->authorize('create', ApplicationCategory::class);

        $this->reset(['editing_id', 'name', 'is_active']);
        $this->is_active = true;
        $this->resetValidation();

        $this->modal('category-form')->show();
    }

    public function startEdit(int $id): void
    {
        $category = $this->findCategory($id);
        $this->authorize('update', $category);

        $this->editing_id = $category->id;
        $this->name = $category->name;
        $this->is_active = $category->is_active;
        $this->resetValidation();

        $this->modal('category-form')->show();
    }

    public function save(): void
    {
        $category = $this->editing_id ? $this->findCategory($this->editing_id) : null;

        $this->authorize($category ? 'update' : 'create', $category ?? ApplicationCategory::class);

        $validated = $this->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique(ApplicationCategory::class, 'name')
                    ->where('department_id', $this->department_id)
                    ->ignore($category?->id),
            ],
            'is_active' => ['boolean'],
        ], [
            'name.unique' => __('This department already has a category with that name.'),
        ]);

        if ($category === null) {
            $nextSortOrder = (int) (ApplicationCategory::query()->where('department_id', $this->department_id)->max('sort_order')) + 1;

            ApplicationCategory::create([
                'department_id' => $this->department_id,
                'name' => $validated['name'],
                'is_active' => $validated['is_active'],
                'sort_order' => $nextSortOrder,
            ]);
        } else {
            $category->update([
                'name' => $validated['name'],
                'is_active' => $validated['is_active'],
            ]);
        }

        unset($this->categories);
        $this->modal('category-form')->close();
        Flux::toast(variant: 'success', text: $this->editing_id ? __('Category updated.') : __('Category created.'));
    }

    public function toggleActive(int $id): void
    {
        $category = $this->findCategory($id);
        $this->authorize('update', $category);

        $category->update(['is_active' => ! $category->is_active]);

        unset($this->categories);
        Flux::toast(variant: 'success', text: $category->is_active ? __('Category activated.') : __('Category deactivated.'));
    }

    public function moveUp(int $id): void
    {
        $this->swapSortOrder($id, 'previous');
    }

    public function moveDown(int $id): void
    {
        $this->swapSortOrder($id, 'next');
    }

    private function swapSortOrder(int $id, string $direction): void
    {
        $category = $this->findCategory($id);
        $this->authorize('update', $category);

        $siblingQuery = ApplicationCategory::query()->where('department_id', $category->department_id);

        $sibling = $direction === 'previous'
            ? $siblingQuery->where('sort_order', '<', $category->sort_order)->orderBy('sort_order', 'desc')->first()
            : $siblingQuery->where('sort_order', '>', $category->sort_order)->orderBy('sort_order')->first();

        if ($sibling === null) {
            return;
        }

        [$categorySortOrder, $siblingSortOrder] = [$category->sort_order, $sibling->sort_order];

        $category->update(['sort_order' => $siblingSortOrder]);
        $sibling->update(['sort_order' => $categorySortOrder]);

        unset($this->categories);
    }

    private function findCategory(int $id): ApplicationCategory
    {
        return ApplicationCategory::query()->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.super-admin.categories.index');
    }
}
