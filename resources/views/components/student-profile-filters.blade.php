@props([
    'programs',
    'batches',
])

<div class="flex min-w-0 flex-1 basis-44 flex-col gap-1">
    <flux:input wire:model.live.debounce.400ms="roll_no" :label="__('Roll number')" :placeholder="__('e.g. F22-171')" />
</div>

<div class="flex min-w-0 flex-1 basis-48 flex-col gap-1">
    <flux:select wire:model.live="program_id" :label="__('Program')">
        <flux:select.option value="">{{ __('All programs') }}</flux:select.option>
        @foreach ($programs as $program)
            <flux:select.option :value="(string) $program->id" wire:key="filter-program-{{ $program->id }}">{{ $program->name }}</flux:select.option>
        @endforeach
    </flux:select>
</div>

<div class="flex min-w-0 flex-1 basis-36 flex-col gap-1">
    <flux:select wire:model.live="batch" :label="__('Batch')">
        <flux:select.option value="">{{ __('All batches') }}</flux:select.option>
        @foreach ($batches as $code => $label)
            <flux:select.option :value="$code" wire:key="filter-batch-{{ $code }}">{{ $label }}</flux:select.option>
        @endforeach
    </flux:select>
</div>
