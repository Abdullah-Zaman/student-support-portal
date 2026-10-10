<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Categories')" :subtitle="__('Application categories, per department.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate" :disabled="$department_id === ''">{{ __('New category') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-end gap-4 rounded-[14px] border border-border bg-white px-6 py-5">
        <div class="flex min-w-0 flex-1 basis-56 flex-col gap-1">
            <flux:select wire:model.live="department_id" :label="__('Department')">
                @foreach ($this->departments as $department)
                    <flux:select.option :value="(string) $department->id">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($this->categories->isEmpty())
        <x-empty-state icon="tag" :title="__('No categories yet')" :description="__('Create the first category for this department.')" />
    @else
        <x-panel :title="__('Categories')">
            <ul class="m-0 flex flex-col">
                @foreach ($this->categories as $category)
                    <li class="flex items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                        <div class="flex items-center gap-3">
                            <div class="flex flex-col gap-0.5">
                                <flux:button variant="ghost" size="sm" icon="chevron-up" wire:click="moveUp({{ $category->id }})" :disabled="$loop->first" />
                                <flux:button variant="ghost" size="sm" icon="chevron-down" wire:click="moveDown({{ $category->id }})" :disabled="$loop->last" />
                            </div>
                            <span class="font-semibold text-ink">{{ $category->name }}</span>
                            <flux:badge :color="$category->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $category->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                        </div>

                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $category->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button variant="ghost" size="sm" wire:click="toggleActive({{ $category->id }})">
                                {{ $category->is_active ? __('Deactivate') : __('Activate') }}
                            </flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-panel>
    @endif

    <flux:modal name="category-form" class="md:w-96">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_id ? __('Edit category') : __('New category') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" maxlength="100" />
            <flux:checkbox wire:model="is_active" :label="__('Active')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="save">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
