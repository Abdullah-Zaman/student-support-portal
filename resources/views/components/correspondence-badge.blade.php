{{--
    Where the Admin Officer's correspondence on an application stands, from
    the latest message to or from the Admin Officer: "With HOD · awaiting
    reply" after the Admin Officer wrote, "HOD replied" (or "remarked")
    after the other side did.
--}}
@props([
    'message',
])

@if ($message)
    @php
        $fromAdmin = $message->from_party === \App\Enums\CorrespondenceParty::AdminOfficer;
        $other = $message->counterpartOf(\App\Enums\CorrespondenceParty::AdminOfficer);
        $label = match (true) {
            $fromAdmin => __('With :party · awaiting reply', ['party' => __($other->label())]),
            $message->type === \App\Enums\CorrespondenceType::Remark => __(':party remarked', ['party' => __($other->label())]),
            default => __(':party replied', ['party' => __($other->label())]),
        };
        $classes = $fromAdmin ? 'bg-[#FFF1D6] text-[#7A4A00]' : 'bg-[#E3F4E8] text-[#1E6B3A]';
    @endphp

    <span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold', $classes]) }} data-test="correspondence-badge">
        <flux:icon :icon="$fromAdmin ? 'clock' : 'chat-bubble-left-right'" variant="micro" class="size-3" />
        {{ $label }}
    </span>
@endif
