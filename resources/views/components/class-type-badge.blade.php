@props([
    'type',
    'showTheory' => false,
])

@php
    $value = $type instanceof \App\Enums\TimetableClassType ? $type->value : $type;

    [$icon, $classes, $label] = match ($value) {
        'lab' => ['computer-desktop', 'bg-[#E4F4F4] text-[#1E5F60]', 'Lab'],
        'theory_lab' => ['beaker', 'bg-[#EFE7F7] text-[#5B3A86]', 'Theory + Lab'],
        default => ['book-open', 'bg-[#EEECEE] text-[#3F3A3E]', 'Theory'],
    };
@endphp

@if ($value !== 'theory' || $showTheory)
    <span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold', $classes]) }}>
        <flux:icon :icon="$icon" variant="micro" class="size-3" />
        {{ __($label) }}
    </span>
@endif
