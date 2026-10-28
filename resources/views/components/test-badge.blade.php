{{-- Marks an account made by the Super Admin's test-user tool. --}}
<span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#FFF1D6] px-2 py-0.5 text-xs font-semibold text-[#7A4A00]']) }} data-test="test-badge">
    <flux:icon icon="beaker" variant="micro" class="size-3" />
    {{ __('Test') }}
</span>
