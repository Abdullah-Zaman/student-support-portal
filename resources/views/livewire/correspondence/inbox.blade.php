<div class="flex flex-col gap-6 py-7" wire:poll.30s.visible>
    <x-page-header :title="__('Inbox')" :subtitle="__('Your private conversations on applications. Unread first.')" />

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

    @if ($this->conversations->isEmpty())
        <x-empty-state icon="inbox" :title="__('Nothing here')" :description="$filter !== '' ? __('Try a different filter.') : __('You have no conversations yet.')" />
    @else
        <x-panel :title="__('Conversations')">
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($this->conversations as $conversation)
                    @php
                        $latest = $conversation->latestMessage;
                        $application = $conversation->application;
                        $isFromMe = $latest !== null && $latest->from_user_id === auth()->id();
                    @endphp
                    <li class="border-t border-border-soft first:border-t-0" wire:key="inbox-{{ $conversation->id }}">
                        <a href="{{ $this->conversationUrl($conversation) }}" class="flex flex-wrap items-start gap-x-4 gap-y-1.5 px-[22px] py-3.5 no-underline hover:bg-page">
                            <span class="flex min-w-0 flex-1 basis-72 flex-col gap-0.5">
                                <span class="flex items-center gap-2 {{ $conversation->unread_count > 0 ? 'font-bold text-ink' : 'font-semibold text-ink-soft' }}">
                                    @if ($conversation->unread_count > 0)
                                        <span class="size-2 shrink-0 rounded-full bg-plum" aria-hidden="true"></span>
                                    @endif
                                    <span class="truncate">{{ $conversation->titleFor(auth()->user()) }}</span>
                                </span>
                                <span class="truncate text-[13px] text-subtle tabular-nums">{{ $application->application_no }} · {{ $application->subject }} · {{ $application->student->user->name }}</span>
                                @if ($latest)
                                    <span class="truncate text-[13px] text-ink-soft">
                                        {{ $latest->senderName() }}: {{ $latest->body ? \Illuminate\Support\Str::limit($latest->body, 90) : __($latest->type->label()) }}
                                    </span>
                                @endif
                            </span>
                            <span class="flex shrink-0 flex-col items-end gap-1">
                                @if ($latest)
                                    <span class="text-[13px] text-subtle">{{ $latest->created_at->format('j M, g:i A') }}</span>
                                @endif
                                @if ($conversation->unread_count > 0)
                                    <flux:badge color="purple" size="sm" data-test="inbox-unread-{{ $conversation->id }}">{{ trans_choice(':count unread|:count unread', $conversation->unread_count) }}</flux:badge>
                                @elseif ($isFromMe)
                                    <flux:badge color="amber" size="sm" icon="clock">{{ __('Awaiting their reply') }}</flux:badge>
                                @endif
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-panel>

        <div>
            {{ $this->conversations->links() }}
        </div>
    @endif
</div>
