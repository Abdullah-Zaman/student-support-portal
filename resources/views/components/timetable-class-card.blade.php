@props([
    'timetableClass',
])

@php
    $color = $timetableClass->courseColor();
@endphp

<article
    {{ $attributes->class(['flex flex-col gap-1.5 rounded-[10px] border px-3 py-2.5']) }}
    style="background-color: {{ $color['bg'] }}; border-color: {{ $color['border'] }};"
>
    <span class="text-[13px] font-semibold tabular-nums" style="color: {{ $color['text'] }};">{{ $timetableClass->timeLabel() }}</span>
    <h3 class="m-0 text-sm leading-snug font-bold text-ink">{{ $timetableClass->course }}</h3>
    <x-class-type-badge :type="$timetableClass->type" class="self-start" />
    <dl class="m-0 flex flex-col gap-0.5 text-[13px] text-ink-soft">
        <div class="flex items-start gap-1.5">
            <dt><flux:icon icon="map-pin" variant="micro" class="mt-0.5 size-3.5 text-subtle" /><span class="sr-only">{{ __('Room') }}</span></dt>
            <dd class="m-0">{{ $timetableClass->room }}@if ($timetableClass->floor) <span class="text-subtle">· {{ $timetableClass->floor }}</span>@endif</dd>
        </div>
        @if ($timetableClass->teacher)
            <div class="flex items-start gap-1.5">
                <dt><flux:icon icon="user" variant="micro" class="mt-0.5 size-3.5 text-subtle" /><span class="sr-only">{{ __('Teacher') }}</span></dt>
                <dd class="m-0">{{ $timetableClass->teacher }}</dd>
            </div>
        @endif
    </dl>
</article>
