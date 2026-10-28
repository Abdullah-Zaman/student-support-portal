@props([
    'schedule',
])

@php
    $now = now(\App\Support\Timetable\StudentSchedule::TIMEZONE);
    $today = $schedule->today($now);
    $state = $schedule->todayState($now);
    $current = $today['current'];
    $next = $today['next'];

    $formatSeconds = function (int $seconds): string {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return ($hours > 0 ? $hours.'h ' : '').$minutes.'m';
    };
@endphp

@if ($schedule->version !== null)
    <x-panel :title="__('Today') . ' · ' . __($now->format('l'))" :attributes="$attributes">
        <x-slot:actions>
            <flux:link :href="route('student.timetable')" class="text-sm font-semibold" wire:navigate>{{ __('Full timetable') }}</flux:link>
        </x-slot:actions>

        @if ($state === \App\Support\Timetable\StudentSchedule::STATE_NO_SECTION)
            <div class="flex flex-col items-start gap-3 px-[22px] py-5" data-test="today-state-no-section">
                <span class="text-sm text-ink-soft">{{ __('Pick your section to see your classes here.') }}</span>
                <flux:button :href="route('student.profile')" variant="primary" size="sm" wire:navigate>{{ __('Choose section') }}</flux:button>
            </div>
        @elseif ($state === \App\Support\Timetable\StudentSchedule::STATE_SECTION_MISSING)
            <div class="flex flex-col items-start gap-3 px-[22px] py-5" data-test="today-state-section-missing">
                <span class="text-sm text-ink-soft">{{ __('Section :section is not in the current timetable. Pick your section again.', ['section' => $schedule->section]) }}</span>
                <flux:button :href="route('student.profile')" variant="primary" size="sm" wire:navigate>{{ __('Choose section') }}</flux:button>
            </div>
        @elseif ($state === \App\Support\Timetable\StudentSchedule::STATE_NO_CLASSES_TODAY)
            <div class="flex items-center gap-3 px-[22px] py-6" data-test="today-state-no-classes">
                <flux:icon icon="sun" class="size-6 text-subtle" />
                <span class="text-sm font-semibold text-ink-soft">{{ __('No classes today.') }}</span>
            </div>
        @elseif ($current === null && $next === null)
            <div class="flex items-center gap-3 px-[22px] py-6">
                <flux:icon icon="check-circle" class="size-6 text-[#1F6A3A]" />
                <span class="text-sm font-semibold text-ink-soft">{{ __("You're done for today.") }}</span>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2">
            @foreach (array_filter(['now' => $current, 'next' => $next]) as $kind => $class)
                @php
                    $isNow = $kind === 'now';
                    $target = $isNow ? $class->endsOn($now) : $class->startsOn($now);
                    $seconds = (int) $now->diffInSeconds($target, true);
                    $color = $class->courseColor();
                @endphp
                <div class="flex gap-3.5 border-t border-border-soft px-[22px] py-4 first:border-t-0 sm:border-t-0 sm:border-l sm:first:border-l-0" data-test="today-{{ $kind }}">
                    <span class="mt-0.5 w-1 shrink-0 self-stretch rounded-full" style="background-color: {{ $color['text'] }};"></span>
                    <div class="flex min-w-0 flex-1 flex-col gap-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$isNow ? 'green' : 'blue'" size="sm">{{ $isNow ? __('Now') : __('Next') }}</flux:badge>
                            <span class="text-[13px] text-subtle tabular-nums">{{ $class->timeLabel() }}</span>
                        </span>
                        <span class="text-[15px] font-bold text-ink">{{ $class->course }}</span>
                        <x-class-type-badge :type="$class->type" class="self-start" />
                        <span class="text-[13px] text-ink-soft">
                            {{ $class->room }}@if ($class->floor) · {{ $class->floor }}@endif
                            @if ($class->teacher)
                                · {{ $class->teacher }}
                            @endif
                        </span>
                        <span
                            class="text-[13px] font-semibold text-plum-link tabular-nums"
                            x-data="{
                                remaining: {{ $seconds }},
                                init() {
                                    const end = Date.now() + this.remaining * 1000;
                                    setInterval(() => { this.remaining = Math.max(0, Math.round((end - Date.now()) / 1000)); }, 1000);
                                },
                                get label() {
                                    const h = Math.floor(this.remaining / 3600), m = Math.floor(this.remaining % 3600 / 60), s = this.remaining % 60;
                                    return (h ? h + 'h ' : '') + m + 'm ' + String(s).padStart(2, '0') + 's';
                                },
                            }"
                        >
                            {{ $isNow ? __('Ends in') : __('Starts in') }}
                            <span x-text="label">{{ $formatSeconds($seconds) }}</span>
                        </span>
                    </div>
                </div>
            @endforeach
            </div>
        @endif
    </x-panel>
@endif
