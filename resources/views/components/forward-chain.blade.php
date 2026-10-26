{{-- Staff-only: forward notes and HOD/Dean remarks. Never render this in a student view. --}}
@props([
    'forwards',
])

@if ($forwards->isEmpty())
    <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('This application has not been forwarded yet.') }}</div>
@else
    <ol class="m-0 flex list-none flex-col p-0">
        @foreach ($forwards as $forward)
            <li class="flex flex-col gap-3 border-t border-border-soft px-[22px] py-4 first:border-t-0" wire:key="forward-{{ $forward->id }}">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:icon icon="arrow-uturn-right" variant="micro" class="size-4 text-plum" />
                    <span class="text-sm font-semibold text-ink">
                        {{ __(':from → :to', ['from' => __($forward->from_role->label()), 'to' => __($forward->to_role->label())]) }}
                    </span>
                    @if ($forward->email_status === \App\Enums\ForwardEmailStatus::PendingEmail)
                        <flux:badge color="amber" size="sm" icon="envelope">{{ __($forward->email_status->label()) }}</flux:badge>
                    @endif
                    <span class="text-[13px] text-subtle">{{ $forward->created_at->format('j M Y, g:i A') }}</span>
                </div>

                <p class="m-0 text-[13px] text-subtle">
                    {{ __('By :name', ['name' => $forward->sender->name]) }}
                    @if ($forward->recipient)
                        · {{ __('to :name', ['name' => $forward->recipient->name]) }}
                    @elseif ($forward->to_email)
                        · {{ __('to :email', ['email' => $forward->to_email]) }}
                    @endif
                </p>

                <p class="m-0 rounded-[10px] bg-page px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ $forward->note }}</p>

                @foreach ($forward->remarks as $remark)
                    @php
                        [$remarkColor, $remarkIcon] = match ($remark->type) {
                            \App\Enums\RemarkType::Recommended => ['green', 'hand-thumb-up'],
                            \App\Enums\RemarkType::NotRecommended => ['red', 'hand-thumb-down'],
                            \App\Enums\RemarkType::Comment => ['zinc', 'chat-bubble-left'],
                        };
                    @endphp
                    <div class="ms-5 flex flex-col gap-1.5 border-s-2 border-border ps-3.5" wire:key="remark-{{ $remark->id }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge :color="$remarkColor" :icon="$remarkIcon" size="sm">{{ __($remark->type->label()) }}</flux:badge>
                            <span class="text-[13px] font-semibold text-ink">{{ __($remark->author_role->label()) }} · {{ $remark->author->name }}</span>
                            <span class="text-[13px] text-subtle">{{ $remark->created_at->format('j M Y, g:i A') }}</span>
                        </div>
                        @if ($remark->body)
                            <p class="m-0 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ $remark->body }}</p>
                        @endif
                    </div>
                @endforeach
            </li>
        @endforeach
    </ol>
@endif
