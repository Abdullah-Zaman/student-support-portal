@php
    $version = $this->timetableVersion;
    $counts = $this->counts;
    $countsByDay = $version->classCountsByDay();
    $unparsedCells = $version->unparsed_cells ?? [];
    $isPublished = $version->status === \App\Enums\TimetableVersionStatus::Published;
    $isRestore = $version->status === \App\Enums\TimetableVersionStatus::Archived;
@endphp

<div class="flex flex-col gap-6 py-7">
    <div>
        <flux:link :href="route($this->routePrefix().'timetable.index')" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold">
            <flux:icon icon="arrow-left" variant="micro" class="size-4" />
            {{ __('All versions') }}
        </flux:link>
    </div>

    <x-page-header
        :title="__('Timetable version :number', ['number' => $version->version_no])"
        :subtitle="$version->original_name.' · '.__('uploaded :date by :name', ['date' => $version->created_at->format('j M Y, g:i A'), 'name' => $version->uploader->name])"
    >
        <x-slot:actions>
            <flux:button icon="arrow-down-tray" :href="route('timetable.download', $version->id)">{{ __('Download original') }}</flux:button>

            @if ($isPublished)
                <flux:badge color="green" icon="check-circle">{{ __('Published :date', ['date' => $version->published_at?->format('j M Y')]) }}</flux:badge>
            @else
                <x-confirm-modal
                    name="publish-version"
                    :heading="$isRestore ? __('Restore this version?') : __('Publish this version?')"
                    :text="__('Students will see this timetable instead of the current one and get a notification.')"
                    :confirm-label="$isRestore ? __('Restore') : __('Publish')"
                    variant="primary"
                    confirm-action="publish"
                >
                    <x-slot:trigger>
                        <flux:button variant="primary" icon="check">{{ $isRestore ? __('Restore this version') : __('Publish') }}</flux:button>
                    </x-slot:trigger>
                </x-confirm-modal>
            @endif
        </x-slot:actions>
    </x-page-header>

    <section aria-label="{{ __('Summary') }}" class="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-4">
        <x-stat-tile :label="__('Classes')" :value="$counts['classes']" />
        <x-stat-tile :label="__('Sections')" :value="count($this->sectionOptions)" />
        <x-stat-tile :label="__('Rooms')" :value="$counts['rooms']" />
        <x-stat-tile :label="__('Teachers')" :value="$counts['teachers']" />
        <x-stat-tile :label="__('Unparsed cells')" :value="$counts['unparsed']" :urgent="$counts['unparsed'] > 0" :note="$counts['unparsed'] > 0 ? __('Fix these in the file and upload again') : __('Every cell was read')" />
    </section>

    <x-card>
        <div class="flex flex-wrap gap-x-8 gap-y-3">
            @foreach (\App\Models\TimetableClass::DAY_NAMES as $dayNumber => $dayName)
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __($dayName) }}</span>
                    <span class="text-[17px] font-bold text-ink tabular-nums">{{ $countsByDay[$dayNumber] ?? 0 }}</span>
                </div>
            @endforeach
        </div>
    </x-card>

    @if ($unparsedCells !== [])
        <x-panel :title="__('Cells that could not be read')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[640px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Sheet') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Cell') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Text') }}</th>
                            <th class="px-[22px] py-3 font-semibold">{{ __('Problem') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($unparsedCells as $cell)
                            <tr class="border-t border-border-soft">
                                <td class="px-[22px] py-3.5 whitespace-nowrap text-ink-soft">{{ $cell['sheet'] }}</td>
                                <td class="px-3 py-3.5 font-semibold text-ink tabular-nums">{{ $cell['cell'] }}</td>
                                <td class="px-3 py-3.5 text-ink-soft">{{ $cell['text'] }}</td>
                                <td class="px-[22px] py-3.5 text-[#7A4A00]">{{ $cell['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>
    @endif

    <x-panel :title="__('Classes')">
        <x-slot:actions>
            <div class="flex flex-wrap gap-3">
                <flux:select wire:model.live="day" size="sm" class="min-w-36">
                    <flux:select.option value="">{{ __('All days') }}</flux:select.option>
                    @foreach (\App\Models\TimetableClass::DAY_NAMES as $dayNumber => $dayName)
                        <flux:select.option :value="(string) $dayNumber">{{ __($dayName) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model.live="section" size="sm" class="min-w-36">
                    <flux:select.option value="">{{ __('All sections') }}</flux:select.option>
                    @foreach ($this->sectionOptions as $sectionName)
                        <flux:select.option :value="$sectionName">{{ $sectionName }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </x-slot:actions>

        @if ($this->classes->isEmpty())
            <div class="px-[22px] py-10 text-center text-sm text-subtle">{{ __('No classes match these filters.') }}</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[960px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Day & time') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Course') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Sections') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Teacher') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Room') }}</th>
                            <th class="px-[22px] py-3 font-semibold">{{ __('Cell') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->classes as $class)
                            <tr class="border-t border-border-soft" wire:key="class-{{ $class->id }}">
                                <td class="px-[22px] py-3 whitespace-nowrap">
                                    <span class="flex flex-col gap-0.5">
                                        <span class="font-semibold text-ink">{{ __($class->dayName()) }}</span>
                                        <span class="text-[13px] text-subtle tabular-nums">{{ $class->timeLabel() }}</span>
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="flex flex-col items-start gap-1">
                                        <span class="font-semibold text-ink">{{ $class->course }}</span>
                                        <x-class-type-badge :type="$class->type" />
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="flex flex-wrap gap-1">
                                        @foreach ($class->sections as $classSection)
                                            <span class="rounded-md bg-avatar px-1.5 py-0.5 text-xs font-semibold text-plum-link">{{ $classSection->section }}</span>
                                        @endforeach
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-ink-soft">{{ $class->teacher ?? '—' }}</td>
                                <td class="px-3 py-3 text-ink-soft">
                                    <span class="flex flex-col gap-0.5">
                                        <span>{{ $class->room }}</span>
                                        @if ($class->floor)
                                            <span class="text-[13px] text-subtle">{{ $class->floor }}</span>
                                        @endif
                                    </span>
                                </td>
                                <td class="px-[22px] py-3 whitespace-nowrap text-[13px] text-subtle">{{ $class->sheet }} · {{ $class->cell }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
</div>
