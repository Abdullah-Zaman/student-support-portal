{{--
    Forward an application. The Livewire component provides forward_target,
    forward_note and a forward() action. $options maps each target this user
    may forward to onto the reason it is unavailable right now (or null).
    $gmailRequiredMessage is set when the user must (re)connect Gmail first.
--}}
@props([
    'options',
    'gmailRequiredMessage' => null,
])

<div class="flex flex-col gap-3 px-[22px] py-4">
    @if ($gmailRequiredMessage)
        <div class="flex flex-col gap-2.5 rounded-[10px] bg-[#FFF1D6] px-3.5 py-3 text-[13px] text-[#7A4A00]" data-test="forward-gmail-required">
            <p class="m-0 flex items-start gap-2">
                <flux:icon icon="envelope" variant="micro" class="mt-0.5 size-4 shrink-0" />
                {{ $gmailRequiredMessage }}
            </p>
            <flux:button size="sm" :href="route('profile.edit')" icon="link" class="self-start" wire:navigate>{{ __('Go to Connected Gmail') }}</flux:button>
        </div>
    @else
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
        <flux:select wire:model="forward_target" :label="__('Forward to')">
            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
            @foreach ($options as $value => $reason)
                @if ($reason === null)
                    <flux:select.option :value="$value">{{ __(\App\Enums\ForwardTarget::from($value)->label()) }}</flux:select.option>
                @endif
            @endforeach
        </flux:select>

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
                <flux:button variant="primary" icon="arrow-uturn-right" class="w-full">{{ __('Forward') }}</flux:button>
            </x-slot:trigger>
        </x-confirm-modal>
    @endif
    @endif
</div>
