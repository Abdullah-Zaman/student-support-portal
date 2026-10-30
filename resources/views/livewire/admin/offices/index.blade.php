<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Offices')" :subtitle="__('Offices and contacts you can forward applications to by email.')">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreateOffice">{{ __('New office') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->offices->isEmpty())
        <x-empty-state icon="building-office-2" :title="__('No offices yet')" :description="__('Add an office, then its contacts.')" />
    @else
        @foreach ($this->offices as $office)
            <x-panel :title="$office->name.($office->code ? ' ('.$office->code.')' : '')" wire:key="office-{{ $office->id }}">
                <div class="flex flex-wrap items-center justify-between gap-3 px-[22px] py-3.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge :color="$office->is_active ? 'green' : 'zinc'" size="sm">{{ $office->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                        <flux:badge :color="$office->available_to_hod ? 'purple' : 'zinc'" size="sm">{{ $office->available_to_hod ? __('Available to HOD') : __('Not available to HOD') }}</flux:badge>
                        <flux:badge :color="$office->available_to_dean ? 'blue' : 'zinc'" size="sm">{{ $office->available_to_dean ? __('Available to Dean') : __('Not available to Dean') }}</flux:badge>
                    </div>
                    <div class="flex gap-2">
                        <flux:button variant="ghost" size="sm" wire:click="startCreateContact({{ $office->id }})">{{ __('Add contact') }}</flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="startEditOffice({{ $office->id }})">{{ __('Edit') }}</flux:button>
                        <flux:button variant="ghost" size="sm" wire:click="confirmToggle('office', {{ $office->id }})">{{ $office->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                    </div>
                </div>

                <ul class="m-0 flex flex-col">
                    @forelse ($office->contacts->sortBy('name') as $contact)
                        <li class="flex flex-wrap items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3" wire:key="contact-{{ $contact->id }}">
                            <div class="min-w-0">
                                <div class="font-semibold text-ink">{{ $contact->name }}@if ($contact->designation) <span class="font-normal text-subtle">— {{ $contact->designation }}</span>@endif</div>
                                <div class="text-sm text-muted">{{ $contact->email }}</div>
                            </div>
                            <div class="flex items-center gap-2">
                                <flux:badge :color="$contact->is_active ? 'green' : 'zinc'" size="sm">{{ $contact->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                                <flux:button variant="ghost" size="sm" wire:click="startEditContact({{ $contact->id }})">{{ __('Edit') }}</flux:button>
                                <flux:button variant="ghost" size="sm" wire:click="confirmToggle('contact', {{ $contact->id }})">{{ $contact->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                            </div>
                        </li>
                    @empty
                        <li class="border-t border-border-soft px-[22px] py-3 text-sm text-subtle">{{ __('No contacts yet.') }}</li>
                    @endforelse
                </ul>
            </x-panel>
        @endforeach
    @endif

    <flux:modal name="office-form" class="md:w-96">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_office_id ? __('Edit office') : __('New office') }}</flux:heading>

            <flux:input wire:model="office_name" :label="__('Name')" maxlength="100" />
            <flux:input wire:model="office_code" :label="__('Code (optional)')" maxlength="20" />
            <flux:switch wire:model="available_to_hod" :label="__('Available to HOD')" />
            <flux:switch wire:model="available_to_dean" :label="__('Available to Dean')" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="saveOffice">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="contact-form" class="md:w-96">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ $editing_contact_id ? __('Edit contact') : __('Add contact') }}</flux:heading>

            <flux:input wire:model="contact_name" :label="__('Name')" maxlength="100" />
            <flux:input wire:model="contact_designation" :label="__('Designation (optional)')" maxlength="100" />
            <flux:input wire:model="contact_email" type="email" :label="__('Email')" maxlength="255" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="saveContact">{{ __('Save') }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-toggle" class="md:w-96">
        <div class="flex flex-col gap-5">
            <flux:heading size="lg">{{ __('Change status?') }}</flux:heading>
            <flux:text>{{ __('A deactivated office or contact can no longer be chosen when forwarding. Past forwards stay as they are.') }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="applyToggle">{{ __('Confirm') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
