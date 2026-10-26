<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Programs')" :subtitle="__('Program codes form the registration number format, e.g. SU92-BSSEM-F22-171.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate" :disabled="$this->departments->isEmpty()">{{ __('New program') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-end gap-4 rounded-[14px] border border-border bg-white px-6 py-5">
        <div class="flex min-w-0 flex-1 basis-56 flex-col gap-1">
            <flux:select wire:model.live="filter_department_id" :label="__('Department')">
                <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
                @foreach ($this->departments as $department)
                    <flux:select.option :value="(string) $department->id" wire:key="filter-department-{{ $department->id }}">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    @if ($this->programs->isEmpty())
        <x-empty-state icon="academic-cap" :title="__('No programs yet')" :description="__('Create the first program for this department.')" />
    @else
        <x-panel :title="__('Programs')">
            <ul class="m-0 flex flex-col">
                @foreach ($this->programs as $program)
                    <li wire:key="program-{{ $program->id }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                        <div class="flex min-w-0 flex-col gap-0.5">
                            <div class="flex flex-wrap items-center gap-3">
                                <span class="font-semibold text-ink">{{ $program->name }}</span>
                                <span class="text-xs font-semibold text-subtle">{{ $program->code }}</span>
                                <flux:badge :color="$program->is_active ? 'green' : 'zinc'" size="sm">
                                    {{ $program->is_active ? __('Active') : __('Inactive') }}
                                </flux:badge>
                            </div>
                            <span class="text-xs text-subtle">
                                {{ $program->department->name }} · {{ trans_choice(':count student|:count students', $program->students_count) }} · {{ \App\Support\RegistrationNumber::example($program->code) }}
                            </span>
                        </div>

                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $program->id }})">{{ __('Edit') }}</flux:button>
                            @if ($program->is_active)
                                <x-confirm-modal
                                    :name="'confirm-deactivate-program-'.$program->id"
                                    :heading="__('Deactivate :name?', ['name' => $program->name])"
                                    :text="__('New students can no longer register with :code registration numbers. Existing students and their applications keep working.', ['code' => $program->code])"
                                    :confirmLabel="__('Deactivate')"
                                    variant="danger"
                                    :confirmAction="'toggleActive('.$program->id.')'"
                                >
                                    <x-slot:trigger>
                                        <flux:button variant="ghost" size="sm">{{ __('Deactivate') }}</flux:button>
                                    </x-slot:trigger>
                                </x-confirm-modal>
                            @else
                                <x-confirm-modal
                                    :name="'confirm-activate-program-'.$program->id"
                                    :heading="__('Activate :name?', ['name' => $program->name])"
                                    :text="__('Students can register with :code registration numbers, as long as its department and faculty are active.', ['code' => $program->code])"
                                    :confirmLabel="__('Activate')"
                                    variant="primary"
                                    :confirmAction="'toggleActive('.$program->id.')'"
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

    <flux:modal name="program-form" class="md:w-96">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_id ? __('Edit program') : __('New program') }}</flux:heading>

            <flux:select wire:model="department_id" :label="__('Department')">
                @foreach ($this->departments as $department)
                    <flux:select.option :value="(string) $department->id" wire:key="form-department-{{ $department->id }}">{{ $department->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input
                wire:model="code"
                :label="__('Code')"
                maxlength="10"
                placeholder="BSCS"
                class="uppercase"
                :disabled="$this->isCodeLocked"
                :description="$this->isCodeLocked ? __('Students are registered with this code, so it cannot be changed.') : __('As it appears in the registration number.')"
            />
            <flux:input wire:model="name" :label="__('Name')" maxlength="100" :placeholder="__('BS Computer Science')" />
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
