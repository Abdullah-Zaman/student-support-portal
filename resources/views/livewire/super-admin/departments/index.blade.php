<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Departments')" :subtitle="__('Departments belong to a faculty. Deactivating one closes registration for all of its programs.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate" :disabled="$this->faculties->isEmpty()">{{ __('New department') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($activation_warning)
        <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Department not fully set up')">
            <flux:callout.text>{{ $activation_warning }}</flux:callout.text>
            <x-slot name="controls">
                <flux:button icon="x-mark" variant="ghost" size="sm" wire:click="dismissActivationWarning" :aria-label="__('Dismiss')" />
            </x-slot>
        </flux:callout>
    @endif

    <div class="flex flex-wrap items-end gap-4 rounded-[14px] border border-border bg-white px-6 py-5">
        <div class="flex min-w-0 flex-1 basis-56 flex-col gap-1">
            <flux:select wire:model.live="filter_faculty_id" :label="__('Faculty')">
                <flux:select.option value="">{{ __('All faculties') }}</flux:select.option>
                @foreach ($this->faculties as $faculty)
                    <flux:select.option :value="(string) $faculty->id" wire:key="filter-faculty-{{ $faculty->id }}">{{ $faculty->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($this->departments->isEmpty())
        <x-empty-state icon="building-office" :title="__('No departments yet')" :description="__('Create the first department for this faculty.')" />
    @else
        <x-panel :title="__('Departments')">
            <ul class="m-0 flex flex-col">
                @foreach ($this->departments as $department)
                    <li wire:key="department-{{ $department->id }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-semibold text-ink">{{ $department->name }}</span>
                                <span class="text-xs font-semibold text-subtle">{{ $department->code }}</span>
                                <flux:badge :color="$department->is_active ? 'green' : 'zinc'" size="sm">
                                    {{ $department->is_active ? __('Active') : __('Inactive') }}
                                </flux:badge>
                            </div>
                            <span class="text-xs text-subtle">
                                {{ $department->faculty->name }} · {{ trans_choice(':count program|:count programs', $department->programs_count) }}
                            </span>
                        </div>

                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $department->id }})">{{ __('Edit') }}</flux:button>
                            @if ($department->is_active)
                                <x-confirm-modal
                                    :name="'confirm-deactivate-department-'.$department->id"
                                    :heading="__('Deactivate :name?', ['name' => $department->name])"
                                    :text="__('New students can no longer register for its programs. Existing students and their applications keep working.')"
                                    :confirmLabel="__('Deactivate')"
                                    variant="danger"
                                    :confirmAction="'toggleActive('.$department->id.')'"
                                >
                                    <x-slot:trigger>
                                        <flux:button variant="ghost" size="sm">{{ __('Deactivate') }}</flux:button>
                                    </x-slot:trigger>
                                </x-confirm-modal>
                            @else
                                <x-confirm-modal
                                    :name="'confirm-activate-department-'.$department->id"
                                    :heading="__('Activate :name?', ['name' => $department->name])"
                                    :text="__('Students can register for its active programs, as long as its faculty is active.')"
                                    :confirmLabel="__('Activate')"
                                    variant="primary"
                                    :confirmAction="'toggleActive('.$department->id.')'"
                                >
                                    <x-slot:trigger>
                                        <flux:button variant="ghost" size="sm">{{ __('Activate') }}</flux:button>
                                    </x-slot:trigger>
                                </x-confirm-modal>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-panel>
    @endif

    <flux:modal name="department-form" class="md:w-96">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_id ? __('Edit department') : __('New department') }}</flux:heading>

            <flux:select wire:model="faculty_id" :label="__('Faculty')">
                @foreach ($this->faculties as $faculty)
                    <flux:select.option :value="(string) $faculty->id" wire:key="form-faculty-{{ $faculty->id }}">{{ $faculty->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input wire:model="name" :label="__('Name')" maxlength="100" :placeholder="__('Computer Science')" />
            <flux:input wire:model="code" :label="__('Code')" maxlength="20" placeholder="CS" class="uppercase" />
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
