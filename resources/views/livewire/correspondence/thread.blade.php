{{-- Staff-only correspondence thread. Never render this in a student view. --}}
<div id="correspondence" wire:poll.30s.visible="refreshThread">
    <x-panel :title="__('Correspondence')">
        @if ($this->gmailNotice)
            <div class="flex flex-wrap items-center gap-3 border-b border-border-soft bg-[#FFF1D6] px-[22px] py-3 text-[13px] text-[#7A4A00]" role="status" data-test="correspondence-gmail-notice">
                <flux:icon icon="envelope" variant="micro" class="size-4 shrink-0" />
                <span class="min-w-0 flex-1">{{ $this->gmailNotice }}</span>
                <flux:button size="sm" :href="route('google.connect')" icon="link">{{ __('Connect Gmail') }}</flux:button>
            </div>
        @endif

        @if ($this->messages->isEmpty())
            <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No correspondence yet. Forward the application to start a thread.') }}</div>
        @else
            <ol class="m-0 flex list-none flex-col p-0">
                @foreach ($this->messages as $message)
                    @php
                        [$typeColor, $typeIcon] = match ($message->type) {
                            \App\Enums\CorrespondenceType::Forward => ['purple', 'arrow-uturn-right'],
                            \App\Enums\CorrespondenceType::Reply => ['blue', 'chat-bubble-left-right'],
                            \App\Enums\CorrespondenceType::Remark => ['zinc', 'pencil-square'],
                            \App\Enums\CorrespondenceType::LoggedReply => ['amber', 'inbox-arrow-down'],
                        };
                        $remarkColor = match ($message->remark_type) {
                            \App\Enums\RemarkType::Recommended => 'green',
                            \App\Enums\RemarkType::NotRecommended => 'red',
                            default => 'zinc',
                        };
                    @endphp
                    <li class="flex flex-col gap-2.5 border-t border-border-soft px-[22px] py-4 first:border-t-0" wire:key="correspondence-{{ $message->id }}" data-test="correspondence-{{ $message->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$typeColor" :icon="$typeIcon" size="sm">{{ __($message->type->label()) }}</flux:badge>
                            @if ($message->remark_type)
                                <flux:badge :color="$remarkColor" size="sm">{{ __($message->remark_type->label()) }}</flux:badge>
                            @endif
                            <span class="text-[13px] text-subtle">{{ $message->created_at->format('j M Y, g:i A') }}</span>
                        </div>

                        <p class="m-0 text-sm text-ink">
                            <span class="font-semibold">{{ $message->senderName() }}</span>
                            <span class="text-subtle">({{ __($message->from_party->label()) }})</span>
                            <span class="text-subtle">→</span>
                            <span class="font-semibold">{{ $message->recipientName() }}</span>
                            <span class="text-subtle">({{ __($message->to_party->label()) }})</span>
                        </p>

                        @if ($message->body)
                            <p class="m-0 rounded-[10px] bg-page px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ $message->body }}</p>
                        @endif

                        @if ($message->attachments->isNotEmpty())
                            <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
                                @foreach ($message->attachments as $attachment)
                                    <li>
                                        <flux:button size="sm" variant="ghost" icon="paper-clip" :href="route('correspondence.attachments.show', $attachment)">
                                            {{ $attachment->original_name }}
                                        </flux:button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[13px] text-subtle">
                            @if ($message->type === \App\Enums\CorrespondenceType::LoggedReply)
                                <span data-test="logged-by-{{ $message->id }}">{{ __('Logged by :name', ['name' => $message->author->name]) }}</span>
                            @elseif ($message->email_status !== \App\Enums\CorrespondenceEmailStatus::NotEmailed)
                                <flux:badge :color="$message->email_status->color()" size="sm" icon="envelope" data-test="email-status-{{ $message->id }}">{{ __($message->email_status->label()) }}</flux:badge>
                            @endif

                            @if ($message->to_party !== \App\Enums\CorrespondenceParty::Registrar)
                                @if ($message->seen_at)
                                    <span class="inline-flex items-center gap-1" data-test="seen-{{ $message->id }}">
                                        <flux:icon icon="eye" variant="micro" class="size-3.5" />
                                        {{ __('Seen :time', ['time' => $message->seen_at->format('j M, g:i A')]) }}
                                    </span>
                                @else
                                    <span>{{ __('Not seen yet') }}</span>
                                @endif
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
                                    {{ $message->email_status === \App\Enums\CorrespondenceEmailStatus::Failed ? __('Retry') : __('Send now') }}
                                </flux:button>
                            @endcan
                        </div>

                        @if ($message->email_error && in_array($message->email_status, [\App\Enums\CorrespondenceEmailStatus::Failed, \App\Enums\CorrespondenceEmailStatus::AwaitingGmail], true))
                            <p class="m-0 text-[13px] text-red-700" data-test="email-error-{{ $message->id }}">{{ $message->email_error }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($this->replyTargets !== [])
            <form wire:submit="reply" class="flex flex-col gap-3 border-t border-border-soft px-[22px] py-4" data-test="correspondence-reply-form">
                @if (count($this->replyTargets) > 1)
                    <flux:select wire:model="reply_to" :label="__('Reply to')">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($this->replyTargets as $party)
                            <flux:select.option :value="$party->value">{{ __($party->label()) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @else
                    <p class="m-0 text-sm text-subtle">{{ __('Reply to the :party', ['party' => __($this->replyTargets[0]->label())]) }}</p>
                @endif

                <flux:textarea wire:model="reply_body" :label="__('Reply')" rows="3" maxlength="{{ \App\Livewire\Correspondence\Thread::BODY_MAX_LENGTH }}" />

                <flux:input type="file" wire:model="reply_attachments" multiple :label="__('Attachments (optional)')" />
                <flux:description>{{ __('PDF, JPG, PNG, DOC or DOCX. Up to 3 files, 5 MB each.') }}</flux:description>

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

                <flux:button type="submit" variant="primary" icon="paper-airplane" class="self-end" wire:loading.attr="disabled" wire:target="reply,reply_attachments">
                    {{ __('Send reply') }}
                </flux:button>
            </form>
        @endif

        @can('logExternalReply', $application)
            <div class="flex justify-end border-t border-border-soft px-[22px] py-3">
                <flux:modal.trigger name="log-external-reply">
                    <flux:button size="sm" icon="inbox-arrow-down" data-test="log-external-reply">{{ __('Log external reply') }}</flux:button>
                </flux:modal.trigger>
            </div>

            <flux:modal name="log-external-reply" class="md:w-[32rem]">
                <form wire:submit="logExternalReply" class="space-y-5">
                    <div>
                        <flux:heading size="lg">{{ __('Log the Registrar\'s reply') }}</flux:heading>
                        <flux:subheading>{{ __('Record a reply the Registrar sent by email. It is shown in the thread as logged by you and is not emailed.') }}</flux:subheading>
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
    </x-panel>
</div>
