{{--
    Forward an application. The Livewire component provides forward_target,
    forward_note and a forward() action. $options maps each target this user
    may forward to onto the reason it is unavailable right now (or null).
--}}
@props([
    'options',
])

<div class="flex flex-col gap-3 px-[22px] py-4">
    @foreach ($options as $value => $reason)
        @if ($reason)
            <p class="m-0 flex items-start gap-2 rounded-[10px] bg-[#FFF1D6] px-3 py-2.5 text-[13px] text-[#7A4A00]" data-test="forward-unavailable-{{ $value }}">
                <flux:icon icon="exclamation-triangle" variant="micro" class="mt-0.5 size-4 shrink-0" />
                {{ $reason }}
            </p>
        @endif
    @endforeach

    @if (collect($options)->filter(fn ($reason) => $reason === null)->isEmpty())
        <p class="m-0 text-sm text-subtle">{{ __('There is no one to forward this application to right now.') }}</p>
    @else
        <flux:select wire:model.live="forward_target" :label="__('Forward to')">
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach ($options as $value => $reason)
                @if ($reason === null)
                    <flux:select.option :value="$value">{{ __(\App\Enums\ForwardTarget::from($value)->label()) }}</flux:select.option>
                @endif
            @endforeach
        </flux:select>

        @if ($this->forward_target === \App\Enums\ForwardTarget::Office->value)
            <flux:select wire:model.live="forward_office_id" :label="__('Office')">
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach ($this->forwardOffices as $office)
                    <flux:select.option :value="(string) $office->id">{{ $office->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model="forward_contact_id" :label="__('Contact')" :disabled="$this->forwardContacts->isEmpty()">
                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                @foreach ($this->forwardContacts as $contact)
                    <flux:select.option :value="(string) $contact->id">{{ $contact->name }}{{ $contact->designation ? ' — '.$contact->designation : '' }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif

        <flux:textarea wire:model="forward_note" :label="__('Note')" rows="3" maxlength="1000" :placeholder="__('Why you are forwarding it and what you need.')" />

        <x-confirm-modal
            name="confirm-forward"
            :heading="__('Forward this application?')"
            :text="__('The student will only see where it was forwarded, never your note.')"
            :confirmLabel="__('Forward')"
            variant="primary"
            confirmAction="forward"
        >
            <x-slot:trigger>
                <flux:button variant="primary" icon="arrow-uturn-right" class="w-full" wire:loading.attr="disabled" wire:target="forward">{{ __('Forward') }}</flux:button>
            </x-slot:trigger>
        </x-confirm-modal>
    @endif
</div>
