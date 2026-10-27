<div class="flex flex-col gap-6 py-7" wire:poll.30s.visible>
    <x-page-header :title="__('Inbox')" :subtitle="__('Correspondence on applications you are part of. Unread first.')" />

    <div class="flex flex-wrap gap-2" role="group" aria-label="{{ __('Filter') }}">
        @foreach (['' => __('All'), 'unread' => __('Unread'), 'awaiting_me' => __('Awaiting my reply'), 'awaiting_them' => __('Awaiting their reply')] as $value => $label)
            <flux:button
                size="sm"
                :variant="$filter === $value ? 'primary' : 'outline'"
                wire:click="$set('filter', '{{ $value }}')"
                data-test="inbox-filter-{{ $value === '' ? 'all' : $value }}"
            >
                {{ $label }}
            </flux:button>
        @endforeach
    </div>

    @if ($this->applications->isEmpty())
        <x-empty-state icon="inbox" :title="__('Nothing here')" :description="$filter !== '' ? __('Try a different filter.') : __('No correspondence involves you yet.')" />
    @else
        <x-panel :title="__('Threads')">
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($this->applications as $application)
                    @php($latest = $application->latestCorrespondence)
                    <li class="border-t border-border-soft first:border-t-0" wire:key="inbox-{{ $application->id }}">
                        <a href="{{ $this->applicationUrl($application) }}" class="flex flex-wrap items-start gap-x-4 gap-y-1.5 px-[22px] py-3.5 no-underline hover:bg-page">
                            <span class="flex min-w-0 flex-1 basis-72 flex-col gap-0.5">
                                <span class="flex items-center gap-2 {{ $application->unread_count > 0 ? 'font-bold text-ink' : 'font-semibold text-ink-soft' }}">
                                    @if ($application->unread_count > 0)
                                        <span class="size-2 shrink-0 rounded-full bg-plum" aria-hidden="true"></span>
                                    @endif
                                    <span class="truncate">{{ $application->subject }}</span>
                                </span>
                                <span class="text-[13px] text-subtle tabular-nums">{{ $application->application_no }} · {{ $application->student->user->name }}</span>
                                @if ($latest)
                                    <span class="truncate text-[13px] text-ink-soft">
                                        {{ __($latest->type->label()) }} · {{ $latest->senderName() }} ({{ __($latest->from_party->label()) }}) → {{ __($latest->to_party->label()) }}@if ($latest->body): {{ \Illuminate\Support\Str::limit($latest->body, 90) }}@endif
                                    </span>
                                @endif
                            </span>
                            <span class="flex shrink-0 flex-col items-end gap-1">
                                @if ($latest)
                                    <span class="text-[13px] text-subtle">{{ $latest->created_at->format('j M, g:i A') }}</span>
                                @endif
                                @if ($application->unread_count > 0)
                                    <flux:badge color="purple" size="sm" data-test="inbox-unread-{{ $application->id }}">{{ trans_choice(':count unread|:count unread', $application->unread_count) }}</flux:badge>
                                @elseif ($this->isFromMe($latest))
                                    <flux:badge color="amber" size="sm" icon="clock">{{ __('Awaiting their reply') }}</flux:badge>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-panel>

        <div>
            {{ $this->applications->links() }}
        </div>
    @endif
</div>
