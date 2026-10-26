<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Faculties')" :subtitle="__('Faculties group departments. Deactivating one closes registration for all of its programs.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate">{{ __('New faculty') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->faculties->isEmpty())
        <x-empty-state icon="building-library" :title="__('No faculties yet')" :description="__('Create the first faculty.')" />
    @else
        <x-panel :title="__('Faculties')">
            <ul class="m-0 flex flex-col">
                @foreach ($this->faculties as $faculty)
                    <li wire:key="faculty-{{ $faculty->id }}" class="flex flex-wrap items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                        <div class="flex min-w-0 flex-wrap items-center gap-3">
                            <span class="font-semibold text-ink">{{ $faculty->name }}</span>
                            <span class="text-xs font-semibold text-subtle">{{ $faculty->code }}</span>
                            <flux:badge :color="$faculty->is_active ? 'green' : 'zinc'" size="sm">
                                {{ $faculty->is_active ? __('Active') : __('Inactive') }}
                            </flux:badge>
                            <span class="text-xs text-subtle">{{ trans_choice(':count department|:count departments', $faculty->departments_count) }}</span>
                        </div>

                        <div class="flex gap-2">
                            <flux:button variant="ghost" size="sm" wire:click="startEdit({{ $faculty->id }})">{{ __('Edit') }}</flux:button>
                            @if ($faculty->is_active)
                                <x-confirm-modal
                                    :name="'confirm-deactivate-faculty-'.$faculty->id"
                                    :heading="__('Deactivate :name?', ['name' => $faculty->name])"
                                    :text="__('New students can no longer register for any of its programs. Existing students and their applications keep working.')"
                                    :confirmLabel="__('Deactivate')"
                                    variant="danger"
                                    :confirmAction="'toggleActive('.$faculty->id.')'"
                                >
                                    <x-slot:trigger>
                                        <flux:button variant="ghost" size="sm">{{ __('Deactivate') }}</flux:button>
                                    </x-slot:trigger>
                                </x-confirm-modal>
                            @else
                                <x-confirm-modal
                                    :name="'confirm-activate-faculty-'.$faculty->id"
                                    :heading="__('Activate :name?', ['name' => $faculty->name])"
                                    :text="__('Students can register again for the active programs of its active departments.')"
                                    :confirmLabel="__('Activate')"
                                    variant="primary"
                                    :confirmAction="'toggleActive('.$faculty->id.')'"
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

    <flux:modal name="faculty-form" class="md:w-96">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_id ? __('Edit faculty') : __('New faculty') }}</flux:heading>

            <flux:input wire:model="name" :label="__('Name')" maxlength="100" :placeholder="__('Faculty of Computing')" />
            <flux:input wire:model="code" :label="__('Code')" maxlength="20" placeholder="FOC" class="uppercase" />
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
