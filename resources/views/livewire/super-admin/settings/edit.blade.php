<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('System Settings')" :subtitle="__('Applies immediately across every department.')" />

    <x-panel :title="__('Application form limits')">
        <div class="flex flex-col gap-4 px-[22px] py-5">
            <flux:input type="number" wire:model="subject_max_length" :label="__('Subject max length')" :description="__('Between 50 and 200 characters.')" min="50" max="200" />
            <flux:input type="number" wire:model="body_max_length" :label="__('Body max length')" :description="__('Between 200 and 2000 characters.')" min="200" max="2000" />

            <flux:button variant="primary" class="self-end" wire:click="save">{{ __('Save') }}</flux:button>
        </div>
    </x-panel>
</div>
