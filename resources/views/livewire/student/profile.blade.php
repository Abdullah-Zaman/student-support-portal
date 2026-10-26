<div class="flex flex-col gap-6 py-8">
    <x-page-header :title="__('My Profile')" :subtitle="__('Your academic record. Contact the SE Admin Office to change anything other than your semester.')" />

    <x-card class="max-w-2xl">
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <div class="flex flex-col gap-1">
                <span class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Full name') }}</span>
                <span class="text-[15px] font-semibold text-ink">{{ auth()->user()->name }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Registration no.') }}</span>
                <span class="text-[15px] font-semibold text-ink tabular-nums">{{ auth()->user()->student->registration_no }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('University email') }}</span>
                <span class="text-[15px] font-semibold text-ink">{{ auth()->user()->email }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Program') }}</span>
                <span class="text-[15px] font-semibold text-ink">{{ auth()->user()->student->program->name }}</span>
            </div>

            <div class="flex flex-col gap-1">
                <span class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Batch') }}</span>
                <span class="text-[15px] font-semibold text-ink">{{ auth()->user()->student->batch_label }}</span>
            </div>
        </div>

        <flux:separator class="my-6" />

        <form wire:submit="updateSemester" class="flex max-w-xs flex-col gap-4">
            <flux:select wire:model="current_semester" :label="__('Current semester')" required>
                @foreach (range(1, 8) as $semester)
                    <flux:select.option :value="(string) $semester">{{ $semester }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button type="submit" variant="primary" data-test="update-semester-button">
                {{ __('Save') }}
            </flux:button>
        </form>
    </x-card>
</div>
