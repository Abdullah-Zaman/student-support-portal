@php
    $routePrefix = $this->isSuperAdmin ? 'super-admin.' : 'admin.';
@endphp

<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Applications')" :subtitle="$this->isSuperAdmin ? __('Every application across all departments.') : __('Every application submitted to your department.')" />

    <div class="flex flex-col gap-4 rounded-[14px] border border-border bg-white px-6 py-5">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex min-w-0 flex-1 basis-64 flex-col gap-1">
                <flux:input wire:model.live.debounce.400ms="search" :label="__('Search')" icon="magnifying-glass" :placeholder="__('Application no., student name or reg. no.')" />
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

            <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
                <flux:select wire:model.live="status" :label="__('Status')">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach ($this->statuses as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
                <flux:select wire:model.live="priority" :label="__('Priority')">
                    <flux:select.option value="">{{ __('All priorities') }}</flux:select.option>
                    @foreach ($this->priorities as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
                <flux:select wire:model.live="category_id" :label="__('Category')">
                    <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                    @foreach ($this->categories as $category)
                        <flux:select.option :value="(string) $category->id">{{ $category->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
                <flux:select wire:model.live="forwarded" :label="__('Forwarded')">
                    <flux:select.option value="">{{ __('All applications') }}</flux:select.option>
                    <flux:select.option value="any">{{ __('Any forward') }}</flux:select.option>
                    @foreach (\App\Enums\ForwardTarget::cases() as $target)
                        <flux:select.option :value="$target->value">{{ __($target->badgeLabel()) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex min-w-0 flex-1 basis-28 flex-col gap-1">
                <flux:select wire:model.live="semester" :label="__('Semester')">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach (range(1, 8) as $semester)
                        <flux:select.option :value="(string) $semester">{{ $semester }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-4">
            <x-student-profile-filters :programs="$this->programs" :batches="$this->batches" />

            <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
                <flux:input type="date" wire:model.live="date_from" :label="__('From')" />
            </div>
            <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
                <flux:input type="date" wire:model.live="date_to" :label="__('To')" />
            </div>

            @if ($this->hasActiveFilters)
                <flux:button variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
            @endif
        </div>
    </div>

    @if ($this->applications->isEmpty())
        <x-empty-state icon="inbox" :title="__('No applications found')" :description="__('Try a different search or filter.')" />
    @else
        <x-panel :title="__('Applications')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[820px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Application') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Student') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Sem.') }}</th>
                            <th class="px-3 py-3 font-semibold">
                                <button type="button" wire:click="sortBy('priority')" class="flex items-center gap-1 font-semibold uppercase">
                                    {{ __('Priority') }}
                                    @if ($sort === 'priority')
                                        <flux:icon :icon="$direction === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-3 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-3 py-3 font-semibold">
                                <button type="button" wire:click="sortBy('created_at')" class="flex items-center gap-1 font-semibold uppercase">
                                    {{ __('Submitted') }}
                                    @if ($sort === 'created_at')
                                        <flux:icon :icon="$direction === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                                    @endif
                                </button>
                            </th>
                            <th class="px-[22px] py-3 font-semibold"><span class="sr-only">{{ __('Action') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->applications as $application)
                            <tr class="border-t border-border-soft">
                                <td class="px-[22px] py-3.5">
                                    <a href="{{ route($routePrefix.'applications.show', $application) }}" wire:navigate class="flex flex-col gap-0.5 no-underline">
                                        <span class="flex items-center gap-2">
                                            <span class="font-semibold text-ink">{{ $application->subject }}</span>
                                            @if ($application->unreadStudentResponses->isNotEmpty())
                                                <flux:badge color="amber" size="sm">{{ __('New response') }}</flux:badge>
                                            @endif
                                        </span>
                                        <span class="text-[13px] text-subtle tabular-nums">{{ $application->application_no }} &middot; {{ $application->category->name }}</span>
                                    </a>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium text-ink">{{ $application->student->user->name }}</span>
                                        <span class="text-[13px] text-subtle tabular-nums">{{ $application->student->registration_no }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 tabular-nums text-ink-soft">{{ $application->semester_at_submission }}</td>
                                <td class="px-3 py-3.5"><x-priority-badge :priority="$application->priority" /></td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col items-start gap-1">
                                        <x-status-badge :status="$application->status" />
                                        @php($adminCorrespondence = $application->latestAdminCorrespondence)
                                        <x-correspondence-badge :message="$adminCorrespondence" />
                                        @if (! $adminCorrespondence || ($application->forwarded_to && \App\Enums\CorrespondenceParty::fromForwardTarget($application->forwarded_to) !== $adminCorrespondence->counterpartOf(\App\Enums\CorrespondenceParty::AdminOfficer)))
                                            <x-forwarded-badge :target="$application->forwarded_to" />
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-ink-soft">{{ $application->created_at->format('j M, g:i A') }}</td>
                                <td class="px-[22px] py-3.5 text-right">
                                    <flux:button :href="route($routePrefix.'applications.show', $application)" variant="ghost" size="sm" wire:navigate>{{ __('Open') }}</flux:button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        <div>
            {{ $this->applications->links() }}
        </div>
    @endif
</div>
