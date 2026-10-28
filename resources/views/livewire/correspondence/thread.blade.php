{{-- Staff-only conversations. Never render this in a student or Super Admin view. --}}
@php
    $viewer = auth()->user();
    $selected = $this->selected;
    $emailStatus = \App\Enums\CorrespondenceEmailStatus::class;
@endphp
<div id="correspondence" wire:poll.30s.visible="refreshThread">
    <x-panel :title="__('Conversations')">
        @if ($this->gmailNotice)
            <div class="flex flex-wrap items-center gap-3 border-b border-border-soft bg-[#FFF1D6] px-[22px] py-3 text-[13px] text-[#7A4A00]" role="status" data-test="correspondence-gmail-notice">
                <flux:icon icon="envelope" variant="micro" class="size-4 shrink-0" />
                <span class="min-w-0 flex-1">{{ $this->gmailNotice }}</span>
                <flux:button size="sm" :href="route('google.connect')" icon="link">{{ __('Connect Gmail') }}</flux:button>
            </div>
        @endif

        @foreach ($this->forwardStatusLines as $line)
            <p class="m-0 flex items-center gap-2 border-b border-border-soft px-[22px] py-2.5 text-[13px] text-subtle" data-test="forward-status-line">
                <flux:icon icon="arrow-uturn-right" variant="micro" class="size-3.5 shrink-0" />
                {{ $line }}
            </p>
        @endforeach

        @if ($this->conversations->isEmpty())
            <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No conversations yet. Forward the application to start one.') }}</div>
        @else
            <div class="flex min-h-[26rem] flex-col lg:flex-row">
                {{-- Conversation list --}}
                <ul class="m-0 {{ $showChat ? 'hidden lg:flex' : 'flex' }} w-full list-none flex-col border-border-soft p-0 lg:w-72 lg:shrink-0 lg:border-r" data-test="conversation-list">
                    @foreach ($this->conversations as $conversation)
                        <li wire:key="conversation-{{ $conversation->id }}">
                            <button
                                type="button"
                                wire:click="selectConversation({{ $conversation->id }})"
                                class="flex w-full cursor-pointer items-start gap-3 border-0 border-b border-border-soft px-4 py-3 text-left {{ $conversation->id === $selectedId ? 'bg-page' : 'bg-white hover:bg-page' }}"
                                data-test="conversation-{{ $conversation->id }}"
                            >
                                <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                                    <span class="truncate text-sm {{ $conversation->unread_count > 0 ? 'font-bold text-ink' : 'font-semibold text-ink-soft' }}">{{ $conversation->titleFor($viewer) }}</span>
                                    @if ($conversation->latestMessage)
                                        <span class="truncate text-[13px] text-subtle">{{ $conversation->latestMessage->body ? \Illuminate\Support\Str::limit($conversation->latestMessage->body, 50) : __($conversation->latestMessage->type->label()) }}</span>
                                    @endif
                                </span>
                                @if ($conversation->unread_count > 0)
                                    <flux:badge color="purple" size="sm" data-test="conversation-unread-{{ $conversation->id }}">{{ $conversation->unread_count }}</flux:badge>
                                @endif
                            </button>
                        </li>
                    @endforeach
                </ul>

                {{-- Chat --}}
                <div class="{{ $showChat ? 'flex' : 'hidden lg:flex' }} min-w-0 flex-1 flex-col" data-test="conversation-chat">
                    @if ($selected)
                        <div class="flex items-center gap-2 border-b border-border-soft px-4 py-3">
                            <flux:button size="sm" variant="ghost" icon="chevron-left" class="lg:hidden" wire:click="backToList" :aria-label="__('Back to conversations')" />
                            <span class="truncate text-sm font-semibold text-ink">{{ $selected->titleFor($viewer) }}</span>
                        </div>

                        <ol class="m-0 flex max-h-[32rem] flex-1 list-none flex-col gap-3 overflow-y-auto bg-page px-4 py-4">
                            @php $previousDay = null; @endphp
                            @foreach ($this->messages as $message)
                                @php
                                    $isMine = $message->from_user_id === $viewer->id;
                                    $day = $message->created_at->format('Y-m-d');
                                    $remarkColor = match ($message->remark_type) {
                                        \App\Enums\RemarkType::Recommended => 'green',
                                        \App\Enums\RemarkType::NotRecommended => 'red',
                                        default => 'zinc',
                                    };
                                @endphp
                                @if ($day !== $previousDay)
                                    <li class="self-center rounded-full bg-white px-3 py-0.5 text-xs font-semibold text-subtle" data-test="day-separator">
                                        {{ $message->created_at->isToday() ? __('Today') : $message->created_at->format('j M Y') }}
                                    </li>
                                    @php $previousDay = $day; @endphp
                                @endif
                                <li class="flex flex-col gap-1 {{ $isMine ? 'items-end' : 'items-start' }}" wire:key="message-{{ $message->id }}" data-test="correspondence-{{ $message->id }}">
                                    <div class="max-w-[85%] rounded-[14px] px-3.5 py-2.5 {{ $isMine ? 'bg-plum text-white' : 'border border-border bg-white text-ink-soft' }}">
                                        <p class="m-0 text-xs font-semibold {{ $isMine ? 'text-white/80' : 'text-subtle' }}">{{ $message->senderName() }} · {{ __($message->from_party->label()) }}</p>

                                        @if ($message->type === \App\Enums\CorrespondenceType::Forward || $message->remark_type || $message->type === \App\Enums\CorrespondenceType::LoggedReply)
                                            <div class="mt-1 flex flex-wrap gap-1.5">
                                                @if ($message->type === \App\Enums\CorrespondenceType::Forward)
                                                    <flux:badge color="purple" icon="arrow-uturn-right" size="sm">{{ __('Forward') }}</flux:badge>
                                                @endif
                                                @if ($message->remark_type)
                                                    <flux:badge :color="$remarkColor" size="sm">{{ __('Remark: :type', ['type' => __($message->remark_type->label())]) }}</flux:badge>
                                                @endif
                                                @if ($message->type === \App\Enums\CorrespondenceType::LoggedReply)
                                                    <flux:badge color="amber" icon="inbox-arrow-down" size="sm">{{ __('Logged reply') }}</flux:badge>
                                                @endif
                                            </div>
                                        @endif

                                        @if ($message->body)
                                            <p class="m-0 mt-1 text-sm leading-relaxed whitespace-pre-line">{{ $message->body }}</p>
                                        @endif

                                        @if ($message->attachments->isNotEmpty())
                                            <ul class="m-0 mt-2 flex list-none flex-col gap-1.5 p-0">
                                                @foreach ($message->attachments as $attachment)
                                                    <li>
                                                        <a href="{{ route('correspondence.attachments.show', $attachment) }}" class="flex items-center gap-2 rounded-lg bg-white px-2.5 py-1.5 text-[13px] text-ink no-underline">
                                                            <flux:icon icon="paper-clip" variant="micro" class="size-4 shrink-0" />
                                                            <span class="min-w-0 flex-1 truncate">{{ $attachment->original_name }}</span>
                                                            <span class="shrink-0 text-subtle">{{ \Illuminate\Support\Number::fileSize($attachment->size) }}</span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>

                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-subtle {{ $isMine ? 'justify-end' : '' }}">
                                        <span>{{ $message->created_at->format('g:i A') }}</span>

                                        @if ($message->type === \App\Enums\CorrespondenceType::LoggedReply)
                                            <span data-test="logged-by-{{ $message->id }}">{{ __('Logged by :name', ['name' => $message->author->name]) }}</span>
                                        @elseif ($isMine)
                                            @if ($message->email_status === $emailStatus::Failed)
                                                <span class="inline-flex items-center gap-1 font-semibold text-red-700" data-test="email-status-{{ $message->id }}">
                                                    <flux:icon icon="exclamation-triangle" variant="micro" class="size-3.5" />{{ __('Failed') }}
                                                </span>
                                            @elseif ($message->email_status === $emailStatus::AwaitingGmail)
                                                <span class="inline-flex items-center gap-1 font-semibold text-[#7A4A00]" data-test="email-status-{{ $message->id }}">
                                                    <flux:icon icon="clock" variant="micro" class="size-3.5" />{{ __($message->email_status->label()) }}
                                                </span>
                                            @elseif ($message->email_status === $emailStatus::Sending)
                                                <span class="inline-flex items-center gap-1" data-test="email-status-{{ $message->id }}">
                                                    <flux:icon icon="clock" variant="micro" class="size-3.5" />{{ __('Sending…') }}
                                                </span>
                                            @elseif ($message->seen_at)
                                                <span class="inline-flex items-center gap-1 text-[#1E6B3A]" data-test="seen-{{ $message->id }}" title="{{ $message->seen_at->format('j M, g:i A') }}">
                                                    <flux:icon icon="eye" variant="micro" class="size-3.5" />{{ __('Seen') }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1" data-test="email-status-{{ $message->id }}">
                                                    <flux:icon icon="check" variant="micro" class="size-3.5" />{{ __('Sent') }}
                                                </span>
                                            @endif

                                            @can('retryEmail', $message)
                                                <flux:button
                                                    size="xs"
                                                    icon="arrow-path"
                                                    wire:click="retryEmail({{ $message->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="retryEmail({{ $message->id }})"
                                                    data-test="retry-email-{{ $message->id }}"
                                                >
                                                    {{ $message->email_status === $emailStatus::Failed ? __('Retry') : __('Send now') }}
                                                </flux:button>
                                            @endcan
                                        @endif
                                    </div>

                                    @if ($isMine && $message->email_error && in_array($message->email_status, [$emailStatus::Failed, $emailStatus::AwaitingGmail], true))
                                        <p class="m-0 max-w-[85%] text-xs text-red-700" data-test="email-error-{{ $message->id }}">{{ $message->email_error }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>

                        @can('reply', $selected)
                            <form wire:submit="reply" class="flex flex-col gap-2 border-t border-border-soft px-4 py-3" data-test="correspondence-reply-form">
                                <flux:textarea wire:model="reply_body" rows="2" :placeholder="__('Write a message…')" maxlength="{{ \App\Livewire\Correspondence\Thread::BODY_MAX_LENGTH }}" />

                                @if ($reply_attachments)
                                    <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
                                        @foreach ($reply_attachments as $index => $file)
                                            <li class="flex items-center justify-between gap-3 rounded-lg border border-border-soft bg-page px-3 py-2 text-sm">
                                                <span class="truncate text-ink-soft">{{ $file->getClientOriginalName() }}</span>
                                                <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="removeReplyAttachment({{ $index }})" />
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:input type="file" wire:model="reply_attachments" multiple class="min-w-0 flex-1" />
                                    <flux:button type="submit" variant="primary" icon="paper-airplane" wire:loading.attr="disabled" wire:target="reply,reply_attachments">{{ __('Send') }}</flux:button>
                                </div>
                                <flux:description>{{ __('PDF, JPG, PNG, DOC or DOCX. Up to 3 files, 5 MB each.') }}</flux:description>
                            </form>
                        @endcan

                        @can('logExternalReply', $selected)
                            <div class="flex justify-end border-t border-border-soft px-4 py-3">
                                <flux:modal.trigger name="log-external-reply">
                                    <flux:button size="sm" icon="inbox-arrow-down" data-test="log-external-reply">{{ __('Log external reply') }}</flux:button>
                                </flux:modal.trigger>
                            </div>

                            <flux:modal name="log-external-reply" class="md:w-[32rem]">
                                <form wire:submit="logExternalReply" class="space-y-5">
                                    <div>
                                        <flux:heading size="lg">{{ __('Log the Registrar\'s reply') }}</flux:heading>
                                        <flux:subheading>{{ __('Record a reply the Registrar sent by email. It is shown here as logged by you and is not emailed.') }}</flux:subheading>
                                    </div>

                                    <flux:textarea wire:model="external_body" :label="__('Reply')" rows="5" maxlength="{{ \App\Livewire\Correspondence\Thread::BODY_MAX_LENGTH }}" />

                                    <flux:input type="file" wire:model="external_attachments" multiple :label="__('Attachments (optional)')" />

                                    @if ($external_attachments)
                                        <ul class="m-0 flex list-none flex-col gap-1.5 p-0">
                                            @foreach ($external_attachments as $index => $file)
                                                <li class="flex items-center justify-between gap-3 rounded-lg border border-border-soft bg-page px-3 py-2 text-sm">
                                                    <span class="truncate text-ink-soft">{{ $file->getClientOriginalName() }}</span>
                                                    <flux:button type="button" variant="ghost" size="sm" icon="x-mark" wire:click="removeExternalAttachment({{ $index }})" />
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif

                                    <div class="flex gap-2">
                                        <flux:spacer />
                                        <flux:modal.close>
                                            <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                                        </flux:modal.close>
                                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="logExternalReply,external_attachments">{{ __('Log reply') }}</flux:button>
                                    </div>
                                </form>
                            </flux:modal>
                        @endcan
                    @endif
                </div>
            </div>
        @endif
    </x-panel>
</div>
