<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Forwarded to me')" :subtitle="__('Applications the Admin Office or the HOD forwarded to you for review.')" />

    <div class="flex flex-wrap items-end gap-4 rounded-[14px] border border-border bg-white px-6 py-5">
        <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
            <flux:select wire:model.live="status" :label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($this->statuses as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <x-student-profile-filters :programs="$this->programs" :batches="$this->batches" />

        @if ($this->hasActiveFilters)
            <flux:button variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        @endif
    </div>

    @if ($this->applications->isEmpty())
        <x-empty-state icon="inbox" :title="__('No applications found')" :description="$this->hasActiveFilters ? __('Try a different filter.') : __('Nothing has been forwarded to you yet.')" />
    @else
        <x-panel :title="__('Applications')">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] border-collapse text-sm">
                    <thead>
                        <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                            <th class="px-[22px] py-3 font-semibold">{{ __('Application') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Student') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Status') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('Forwarded') }}</th>
                            <th class="px-3 py-3 font-semibold">{{ __('My remark') }}</th>
                            <th class="px-[22px] py-3 font-semibold"><span class="sr-only">{{ __('Action') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->applications as $application)
                            @php
                                $latestForward = $application->forwards->first();
                                $myRemark = $application->remarks->first();
                            @endphp
                            <tr class="border-t border-border-soft" wire:key="application-{{ $application->id }}">
                                <td class="px-[22px] py-3.5">
                                    <a href="{{ route($this->routePrefix().'applications.show', $application) }}" wire:navigate class="flex flex-col gap-0.5 no-underline">
                                        <span class="font-semibold text-ink">{{ $application->subject }}</span>
                                        <span class="text-[13px] text-subtle tabular-nums">{{ $application->application_no }} &middot; {{ $application->category->name }}</span>
                                    </a>
                                </td>
                                <td class="px-3 py-3.5">
                                    <div class="flex flex-col gap-0.5">
                                        <span class="font-medium text-ink">{{ $application->student->user->name }}</span>
                                        <span class="text-[13px] text-subtle tabular-nums">{{ $application->student->registration_no }} · {{ $application->student->program->code }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3.5"><x-status-badge :status="$application->status" /></td>
                                <td class="px-3 py-3.5 whitespace-nowrap text-ink-soft">
                                    @if ($latestForward)
                                        <span class="flex flex-col gap-0.5">
                                            <span>{{ $latestForward->created_at->format('j M, g:i A') }}</span>
                                            <span class="text-[13px] text-subtle">{{ __('by the :role', ['role' => __($latestForward->from_party->label())]) }}</span>
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-3.5">
                                    @if ($myRemark)
                                        <flux:badge :color="match ($myRemark->remark_type) { \App\Enums\RemarkType::Recommended => 'green', \App\Enums\RemarkType::NotRecommended => 'red', \App\Enums\RemarkType::Comment => 'zinc' }" size="sm">{{ __($myRemark->remark_type?->label() ?? '') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm" icon="clock">{{ __('Awaiting') }}</flux:badge>
                                    @endif
                                </td>
                                <td class="px-[22px] py-3.5 text-right">
                                    <flux:button :href="route($this->routePrefix().'applications.show', $application)" variant="ghost" size="sm" wire:navigate>{{ __('Open') }}</flux:button>
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
