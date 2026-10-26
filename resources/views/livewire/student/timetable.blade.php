@php
    $schedule = $this->schedule;
    $days = $schedule->visibleDays();
    $today = now()->dayOfWeekIso;
    $initialDay = in_array($today, $days, true) ? $today : $days[0];
@endphp

<div class="flex flex-col gap-6 py-8">
    <x-page-header
        :title="__('My Timetable')"
        :subtitle="$schedule->section ? __('Weekly classes for section :section.', ['section' => $schedule->section]) : __('Your weekly classes from the department timetable.')"
    >
        @if ($schedule->section)
            <x-slot:actions>
                <flux:button :href="route('student.profile')" variant="ghost" icon="pencil-square" wire:navigate>{{ __('Change section') }}</flux:button>
            </x-slot:actions>
        @endif
    </x-page-header>

    @if ($schedule->version === null)
        <x-empty-state icon="calendar-days" :title="__('No timetable published yet')" :description="__('The Admin Office has not published this semester\'s timetable. You will get a notification when it is out.')" />
    @elseif ($schedule->section === null)
        <x-empty-state icon="user-group" :title="__('Pick your section')" :description="__('Choose your section in your profile to see your classes.')">
            <x-slot:action>
                <flux:button :href="route('student.profile')" variant="primary" wire:navigate>{{ __('Choose section') }}</flux:button>
            </x-slot:action>
        </x-empty-state>
    @elseif ($schedule->sectionIsMissing())
        <x-empty-state icon="exclamation-circle" :title="__('Section :section is not in the current timetable', ['section' => $schedule->section])" :description="__('The timetable has changed. Pick your section again in your profile.')">
            <x-slot:action>
                <flux:button :href="route('student.profile')" variant="primary" wire:navigate>{{ __('Choose section') }}</flux:button>
            </x-slot:action>
        </x-empty-state>
    @else
        {{-- Mobile and tablet: one day at a time. --}}
        <div x-data="{ day: {{ $initialDay }} }" class="flex flex-col gap-4 lg:hidden">
            <div role="tablist" aria-label="{{ __('Days') }}" class="-mx-1 flex gap-1.5 overflow-x-auto px-1 pb-1">
                @foreach ($days as $day)
                    <button
                        type="button"
                        role="tab"
                        x-on:click="day = {{ $day }}"
                        x-bind:aria-selected="day === {{ $day }}"
                        x-bind:class="day === {{ $day }} ? 'bg-plum text-white border-plum' : 'bg-white text-ink-soft border-border'"
                        class="flex min-h-11 shrink-0 flex-col items-center justify-center rounded-[10px] border px-3.5 text-sm font-semibold"
                    >
                        {{ __(substr(\App\Models\TimetableClass::DAY_NAMES[$day], 0, 3)) }}
                        @if ($day === $today)
                            <span class="text-[11px] font-medium opacity-80">{{ __('Today') }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @foreach ($days as $day)
                @php
                    $dayClasses = $schedule->classesOn($day);
                @endphp
                <section role="tabpanel" x-show="day === {{ $day }}" @if ($day !== $initialDay) x-cloak @endif aria-label="{{ __(\App\Models\TimetableClass::DAY_NAMES[$day]) }}" class="flex flex-col gap-3">
                    @forelse ($dayClasses as $class)
                        <x-timetable-class-card :timetable-class="$class" wire:key="mobile-class-{{ $class->id }}" />
                    @empty
                        <div class="rounded-[14px] border border-border bg-white px-6 py-10 text-center text-sm text-subtle">
                            {{ $day === $today ? __('No classes today.') : __('No classes on :day.', ['day' => __(\App\Models\TimetableClass::DAY_NAMES[$day])]) }}
                        </div>
                    @endforelse
                </section>
            @endforeach
        </div>

        {{-- Desktop: the whole week side by side. --}}
        <div class="hidden gap-3 lg:grid" style="grid-template-columns: repeat({{ count($days) }}, minmax(0, 1fr));">
            @foreach ($days as $day)
                @php
                    $dayClasses = $schedule->classesOn($day);
                @endphp
                <section aria-label="{{ __(\App\Models\TimetableClass::DAY_NAMES[$day]) }}" @class([
                    'flex min-w-0 flex-col gap-2.5 rounded-[14px] border bg-white p-3',
                    'border-plum' => $day === $today,
                    'border-border' => $day !== $today,
                ])>
                    <h2 class="m-0 flex items-center justify-between gap-2 px-1 text-sm font-bold text-ink">
                        {{ __(\App\Models\TimetableClass::DAY_NAMES[$day]) }}
                        @if ($day === $today)
                            <flux:badge color="purple" size="sm">{{ __('Today') }}</flux:badge>
                        @endif
                    </h2>

                    @forelse ($dayClasses as $class)
                        <x-timetable-class-card :timetable-class="$class" wire:key="desktop-class-{{ $class->id }}" />
                    @empty
                        <p class="m-0 px-1 py-6 text-center text-[13px] text-subtle">{{ $day === $today ? __('No classes today.') : __('No classes') }}</p>
                    @endforelse
                </section>
            @endforeach
        </div>
    @endif
</div>
