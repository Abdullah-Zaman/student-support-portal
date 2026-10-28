<div class="flex flex-col gap-6 py-8">
    <x-page-header :title="__('My Profile')" :subtitle="__('Your academic record. Contact the SE Admin Office to change anything other than your semester and section.')" />

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

        @unless (auth()->user()->student->is_alumni)
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

            <flux:separator class="my-6" />
        @endunless

        <div class="flex flex-col gap-1.5">
            <h2 class="m-0 text-[17px] font-bold text-ink">{{ __('Timetable section') }}</h2>
            <p class="m-0 text-sm text-muted">{{ __('Your section decides which classes appear on your timetable and dashboard.') }}</p>
        </div>

        @if ($this->timetable === null)
            <p class="mt-4 mb-0 text-sm text-subtle">{{ __('The timetable has not been published yet. You can pick your section once it is out.') }}</p>
        @else
            <form wire:submit="updateSection" class="mt-4 flex max-w-xs flex-col gap-4">
                <flux:select wire:model="section" :label="__('Section')">
                    <flux:select.option value="">{{ __('Select your section') }}</flux:select.option>
                    @foreach ($this->sectionOptions as $sectionName)
                        <flux:select.option :value="$sectionName">{{ $sectionName }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:checkbox
                    wire:model.live="showAllSections"
                    :label="__('Show all sections of my program')"
                    :description="__('For classes you take from another semester.')"
                />

                @if ($this->sectionOptions === [])
                    <p class="m-0 text-sm text-subtle">{{ __('No sections for your semester in the current timetable. Tick the box above to see all sections of your program.') }}</p>
                @endif

                <flux:button type="submit" variant="primary" data-test="update-section-button">
                    {{ __('Save section') }}
                </flux:button>
            </form>
        @endif
    </x-card>
</div>
