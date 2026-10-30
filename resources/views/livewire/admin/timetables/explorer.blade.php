<div class="flex flex-col gap-6">
    @if ($this->stats === null)
        <x-empty-state icon="magnifying-glass" :title="__('No published timetable')" :description="__('Publish a timetable version (for Super Admin: choose a department) to see its statistics and search it.')" />
    @else
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5" data-test="timetable-stats">
            <x-stat-tile :label="__('Teachers')" :value="$this->stats['teachers']" data-test="stat-teachers" />
            <x-stat-tile :label="__('Rooms and labs')" :value="$this->stats['rooms']" data-test="stat-rooms" />
            <x-stat-tile :label="__('Sections')" :value="$this->stats['sections']" data-test="stat-sections" />
            <x-stat-tile :label="__('Subjects')" :value="$this->stats['subjects']" data-test="stat-subjects" />
            <x-stat-tile :label="__('Classes per week')" :value="$this->stats['classes']" data-test="stat-classes" />
        </div>

        <x-panel :title="__('Search the timetable')">
            <div class="flex flex-col gap-4 p-[22px]">
                <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Search by') }}">
                    @foreach (['teacher' => __('Teacher'), 'subject' => __('Subject'), 'room' => __('Room'), 'section' => __('Section'), 'student' => __('Student (Reg. No.)')] as $key => $label)
                        <flux:button
                            size="sm"
                            :variant="$mode === $key ? 'primary' : 'ghost'"
                            wire:click="$set('mode', '{{ $key }}')"
                            :aria-pressed="$mode === $key ? 'true' : 'false'"
                        >{{ $label }}</flux:button>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-end gap-4">
                    <div class="flex min-w-0 flex-[2_1_16rem] flex-col gap-1">
                        <flux:input wire:model.live.debounce.300ms="term" :label="__('Search')" :placeholder="__('Type to search')" icon="magnifying-glass" maxlength="100" data-test="timetable-search" />
                    </div>
                    <div class="flex min-w-0 flex-1 basis-44 flex-col gap-1">
                        <flux:select wire:model.live="day" :label="__('Day (optional)')">
                            <flux:select.option value="">{{ __('All days') }}</flux:select.option>
                            @foreach ($this->days as $dayNumber)
                                <flux:select.option :value="(string) $dayNumber">{{ __(\App\Models\TimetableClass::DAY_NAMES[$dayNumber]) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                </div>
            </div>
        </x-panel>

        @php
            $results = $this->results;
        @endphp

        @if ($results === null)
            <p class="m-0 text-sm text-subtle">{{ __('Enter a search term to see results.') }}</p>
        @elseif ($results['notFound'])
            <x-empty-state icon="user" :title="__('Student not found')" :description="__('No student with this registration number was found in your department.')" data-test="student-not-found" />
        @else
            @if ($results['student'])
                <x-card data-test="student-summary">
                    <p class="m-0 text-base font-bold text-ink">{{ $results['student']['name'] }}</p>
                    <p class="m-0 text-sm text-muted">{{ $results['student']['registration_no'] }} · {{ __('Semester') }} {{ $results['student']['semester'] }}@if ($results['student']['section']) · {{ $results['student']['section'] }}@endif</p>
                </x-card>
                @if ($results['student']['section'] === null)
                    <x-empty-state icon="calendar-days" :title="__('No section selected')" :description="__('This student has not selected a section in their profile yet, so there is no timetable to show.')" data-test="student-no-section" />
                @endif
            @endif

            @if ($results['items']->isEmpty() && ($results['student'] === null || $results['student']['section'] !== null))
                <p class="m-0 text-sm text-subtle" data-test="no-results">{{ __('No matches found.') }}</p>
            @endif

            @if ($results['total'] > $results['items']->count())
                <p class="m-0 text-sm text-subtle">{{ __('Showing the first :shown of :total matches. Narrow the search to see others.', ['shown' => $results['items']->count(), 'total' => $results['total']]) }}</p>
            @endif

            @foreach ($results['items'] as $item)
                <x-panel :title="$item['name']" wire:key="result-{{ $mode }}-{{ $loop->index }}">
                    @if ($mode === 'teacher')
                        <div class="flex flex-wrap gap-x-6 gap-y-1 border-b border-border-soft px-[22px] py-3 text-sm text-ink-soft" data-test="workload">
                            <span>{{ __('Classes per week') }}: <strong>{{ $item['total'] }}</strong></span>
                            <span>{{ __('Teaching days') }}: <strong>{{ $item['teachingDays'] }}</strong></span>
                            <span>{{ __('Free days') }}: <strong>{{ $item['freeDays'] === [] ? __('None') : collect($item['freeDays'])->map(fn ($d) => __(\App\Models\TimetableClass::DAY_NAMES[$d]))->implode(', ') }}</strong></span>
                            <span>{{ __('Per day') }}: <strong>{{ collect($item['perDay'])->map(fn ($count, $d) => __(\App\Models\TimetableClass::DAY_NAMES[$d]).' '.$count)->implode(' · ') }}</strong></span>
                        </div>
                        @foreach ($item['byDay'] as $dayNumber => $dayClasses)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => __(\App\Models\TimetableClass::DAY_NAMES[$dayNumber]), 'classes' => $dayClasses, 'columns' => ['time', 'course', 'sections', 'room']])
                        @endforeach
                    @elseif ($mode === 'room')
                        @foreach ($item['days'] as $dayNumber => $dayData)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => __(\App\Models\TimetableClass::DAY_NAMES[$dayNumber]), 'classes' => $dayData['booked'], 'columns' => ['time', 'course', 'teacher', 'sections'], 'free' => $dayData['free']])
                        @endforeach
                    @elseif ($mode === 'subject')
                        @php
                            $subjectDays = $item['classes']->groupBy('day');
                        @endphp
                        @foreach ($subjectDays as $dayNumber => $dayClasses)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => __(\App\Models\TimetableClass::DAY_NAMES[$dayNumber]), 'classes' => $dayClasses, 'columns' => ['time', 'teacher', 'sections', 'room']])
                        @endforeach
                    @else
                        @foreach ($item['classes']->groupBy('day') as $dayNumber => $dayClasses)
                            @include('livewire.admin.timetables.partials.class-rows', ['title' => __(\App\Models\TimetableClass::DAY_NAMES[$dayNumber]), 'classes' => $dayClasses, 'columns' => ['time', 'course', 'teacher', 'room']])
                        @endforeach
                    @endif
                </x-panel>
            @endforeach
        @endif
    @endif
</div>
