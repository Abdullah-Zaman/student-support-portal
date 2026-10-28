@php
    $routePrefix = $this->isSuperAdmin ? 'super-admin.' : 'admin.';
@endphp

<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Students')" :subtitle="$this->isSuperAdmin ? __('Every student across all departments.') : __('Every student registered in your department.')" />

    <div class="flex flex-wrap items-end gap-4 rounded-[14px] border border-border bg-white px-6 py-5">
        <div class="flex min-w-0 flex-1 basis-64 flex-col gap-1">
            <flux:input wire:model.live.debounce.400ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('Name, reg. no. or email')" />
        </div>

        @if ($this->isSuperAdmin)
            <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                <flux:select wire:model.live="department_id" :label="__('Department')">
                    <flux:select.option value="">{{ __('All departments') }}</flux:select.option>
                    @foreach ($this->departments as $department)
                        <flux:select.option :value="(string) $department->id">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        @endif

        <x-student-profile-filters :programs="$this->programs" :batches="$this->batches" />

        <div class="flex min-w-0 flex-1 basis-32 flex-col gap-1">
            <flux:select wire:model.live="semester" :label="__('Semester')">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                @foreach (range(1, 8) as $semester)
                    <flux:select.option :value="(string) $semester">{{ $semester }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
            <flux:select wire:model.live="status" :label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($this->statuses as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
            <flux:select wire:model.live="test" :label="__('Test accounts')">
                <flux:select.option value="">{{ __('Show all') }}</flux:select.option>
                <flux:select.option value="only">{{ __('Only test accounts') }}</flux:select.option>
                <flux:select.option value="hide">{{ __('Hide test accounts') }}</flux:select.option>
            </flux:select>
        </div>

        @if ($this->hasActiveFilters)
            <flux:button variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        @endif
    </div>

    @if ($this->students->isEmpty())
        <x-empty-state icon="users" :title="__('No students found')" :description="__('Try a different search or filter.')" />
    @else
        <x-panel :title="__('Students')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[720px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Student') }}</th>
                            @if ($this->isSuperAdmin)
                                <th class="px-3 py-3 font-semibold">{{ __('Department') }}</th>
                            @endif
                            <th class="px-3 py-3 font-semibold">{{ __('Program') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Semester') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-[22px] py-3 font-semibold"><span class="sr-only">{{ __('Action') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->students as $student)
                            <tr class="border-t border-border-soft">
                                <td class="px-[22px] py-3.5">
                                    <a href="{{ route($routePrefix.'students.show', $student) }}" wire:navigate class="flex flex-col gap-0.5 no-underline">
                                        <span class="flex flex-wrap items-center gap-2 font-semibold text-ink">{{ $student->user->name }} @if ($student->user->is_test)<x-test-badge />@endif</span>
                                        <span class="text-[13px] text-subtle tabular-nums">{{ $student->registration_no }}</span>
                                    </a>
                                </td>
                                @if ($this->isSuperAdmin)
                                    <td class="px-3 py-3.5 text-ink-soft">{{ $student->user->department?->name }}</td>
                                @endif
                                <td class="px-3 py-3.5 text-ink-soft">{{ $student->program->name }}</td>
                                <td class="px-3 py-3.5 tabular-nums text-ink-soft">{{ $student->current_semester }}</td>
                                <td class="px-3 py-3.5">
                                    <flux:badge :color="$student->user->status->value === 'active' ? 'green' : ($student->user->status->value === 'suspended' ? 'red' : 'zinc')" size="sm">
                                        {{ \Illuminate\Support\Str::headline($student->user->status->value) }}
                                    </flux:badge>
                                </td>
                                <td class="px-[22px] py-3.5 text-right">
                                    <flux:button :href="route($routePrefix.'students.show', $student)" variant="ghost" size="sm" wire:navigate>{{ __('View') }}</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        <div>
            {{ $this->students->links() }}
        </div>
    @endif
</div>
