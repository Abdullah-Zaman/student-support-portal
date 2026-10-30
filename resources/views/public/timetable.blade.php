@php
    $dayName = fn (int $d): string => __(\App\Models\TimetableClass::DAY_NAMES[$d]);
@endphp
<x-public-layout :title="__('Timetable')" :heading="$department.' · '.__('Timetable')">
    @if ($explorer === null)
        <x-empty-state icon="calendar-days" :title="__('No published timetable')" :description="__('The timetable has not been published yet. Please check back later.')" />
    @else
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-test="timetable-stats">
            <x-stat-tile :label="__('Teachers')" :value="$stats['teachers']" />
            <x-stat-tile :label="__('Rooms and labs')" :value="$stats['rooms']" />
            <x-stat-tile :label="__('Sections')" :value="$stats['sections']" />
            <x-stat-tile :label="__('Subjects')" :value="$stats['subjects']" />
            <x-stat-tile :label="__('Classes per week')" :value="$stats['classes']" />
        </div>

        <x-panel :title="__('Search the timetable')">
            <form method="GET" class="flex flex-wrap items-end gap-4 p-[22px]" data-test="timetable-search-form">
                <label class="flex min-w-0 flex-1 basis-36 flex-col gap-1 text-sm font-medium text-ink-soft">
                    {{ __('Search by') }}
                    <select name="mode" class="rounded-lg border border-[#D9D2D7] bg-white px-3 py-2 text-sm">
                        @foreach (['teacher' => __('Teacher'), 'subject' => __('Subject'), 'room' => __('Room'), 'section' => __('Section')] as $key => $label)
                            <option value="{{ $key }}" @selected($mode === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex min-w-0 flex-[2_1_14rem] flex-col gap-1 text-sm font-medium text-ink-soft">
                    {{ __('Search') }}
                    <input type="search" name="term" value="{{ $term }}" maxlength="100" placeholder="{{ __('Type to search') }}" class="rounded-lg border border-[#D9D2D7] bg-white px-3 py-2 text-sm">
                </label>
                <label class="flex min-w-0 flex-1 basis-36 flex-col gap-1 text-sm font-medium text-ink-soft">
                    {{ __('Day (optional)') }}
                    <select name="day" class="rounded-lg border border-[#D9D2D7] bg-white px-3 py-2 text-sm">
                        <option value="">{{ __('All days') }}</option>
                        @foreach ($days as $d)
                            <option value="{{ $d }}" @selected($day === $d)>{{ $dayName($d) }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="rounded-lg bg-plum px-5 py-2 text-sm font-semibold text-white">{{ __('Search') }}</button>
            </form>
        </x-panel>

        @if (! $searched)
            <p class="m-0 text-sm text-subtle">{{ __('Enter a search term to see results.') }}</p>
        @else
            @if ($items->isEmpty())
                <p class="m-0 text-sm text-subtle" data-test="no-results">{{ __('No matches found.') }}</p>
            @endif

            @if ($total > $items->count())
                <p class="m-0 text-sm text-subtle">{{ __('Showing the first :shown of :total matches. Narrow the search to see others.', ['shown' => $items->count(), 'total' => $total]) }}</p>
            @endif

            @foreach ($items as $item)
                <x-panel :title="$item['name']">
                    @if ($mode === 'teacher')
                        <div class="flex flex-wrap gap-x-6 gap-y-1 border-b border-border-soft px-[22px] py-3 text-sm text-ink-soft" data-test="workload">
                            <span>{{ __('Classes per week') }}: <strong>{{ $item['total'] }}</strong></span>
                            <span>{{ __('Teaching days') }}: <strong>{{ $item['teachingDays'] }}</strong></span>
                            <span>{{ __('Free days') }}: <strong>{{ $item['freeDays'] === [] ? __('None') : collect($item['freeDays'])->map(fn ($d) => $dayName($d))->implode(', ') }}</strong></span>
                            <span>{{ __('Per day') }}: <strong>{{ collect($item['perDay'])->map(fn ($count, $d) => $dayName($d).' '.$count)->implode(' · ') }}</strong></span>
                        </div>
                        @foreach ($item['byDay'] as $d => $dayClasses)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => $dayName($d), 'classes' => $dayClasses, 'columns' => ['time', 'course', 'sections', 'room']])
                        @endforeach
                    @elseif ($mode === 'room')
                        @foreach ($item['days'] as $d => $dayData)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => $dayName($d), 'classes' => $dayData['booked'], 'columns' => ['time', 'course', 'teacher', 'sections'], 'free' => $dayData['free']])
                        @endforeach
                    @elseif ($mode === 'subject')
                        @foreach ($item['classes']->groupBy('day') as $d => $dayClasses)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => $dayName($d), 'classes' => $dayClasses, 'columns' => ['time', 'teacher', 'sections', 'room']])
                        @endforeach
                    @else
                        @foreach ($item['classes']->groupBy('day') as $d => $dayClasses)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => $dayName($d), 'classes' => $dayClasses, 'columns' => ['time', 'course', 'teacher', 'room']])
                        @endforeach
                    @endif
                </x-panel>
            @endforeach
        @endif
    @endif
</x-public-layout>
