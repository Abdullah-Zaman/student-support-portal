@props([
    'target',
])

@if ($target)
    @php
        [$icon, $classes] = match ($target) {
            \App\Enums\ForwardTarget::AdminOfficer => ['arrow-uturn-left', 'bg-[#E3F4E8] text-[#1E6B3A]'],
            \App\Enums\ForwardTarget::Hod => ['arrow-uturn-right', 'bg-[#EFE7F7] text-[#5B3A86]'],
            \App\Enums\ForwardTarget::Dean => ['arrow-uturn-right', 'bg-[#E3EEFB] text-[#1D4F8A]'],
            \App\Enums\ForwardTarget::Registrar => ['paper-airplane', 'bg-[#E4F4F4] text-[#1E5F60]'],
        };
    @endphp

    <span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold', $classes]) }}>
        <flux:icon :icon="$icon" variant="micro" class="size-3" />
        {{ __($target->badgeLabel()) }}
    </span>
@endif
