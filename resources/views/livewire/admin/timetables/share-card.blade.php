<x-panel :title="__('Share timetable')">
    <div class="flex flex-col gap-4 p-[22px]" data-test="share-card">
        <p class="m-0 text-sm text-muted">{{ __('Anyone with this link can view the published timetable without logging in. It shows no student data.') }}</p>

        @if ($this->link === null)
            <div>
                <flux:button variant="primary" icon="link" wire:click="generate" data-test="share-create">{{ __('Create share link') }}</flux:button>
            </div>
        @else
            <div class="flex flex-wrap items-end gap-3" x-data="{ copied: false }">
                <div class="flex min-w-0 flex-[2_1_20rem] flex-col gap-1">
                    <flux:input readonly :label="__('Public link')" :value="$this->link->url()" x-ref="url" data-test="share-url" />
                </div>
                <flux:button
                    icon="clipboard"
                    x-on:click="navigator.clipboard.writeText($refs.url.querySelector('input')?.value ?? $refs.url.value); copied = true; setTimeout(() => copied = false, 2000)"
                >
                    <span x-show="!copied">{{ __('Copy') }}</span>
                    <span x-show="copied" x-cloak>{{ __('Copied') }}</span>
                </flux:button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <x-confirm-modal
                    name="toggle-share-link"
                    :heading="$this->link->is_active ? __('Deactivate this link?') : __('Activate this link?')"
                    :text="$this->link->is_active ? __('The public page will stop working until you activate it again.') : __('The public page will be visible to anyone with the link.')"
                    :confirm-label="$this->link->is_active ? __('Deactivate') : __('Activate')"
                    :variant="$this->link->is_active ? 'danger' : 'primary'"
                    confirm-action="toggleActive"
                >
                    <x-slot name="trigger">
                        <flux:button :icon="$this->link->is_active ? 'pause' : 'play'" data-test="share-toggle">
                            {{ $this->link->is_active ? __('Active: switch off') : __('Inactive: switch on') }}
                        </flux:button>
                    </x-slot>
                </x-confirm-modal>

                <x-confirm-modal
                    name="regenerate-share-link"
                    :heading="__('Generate a new link?')"
                    :text="__('The old link will stop working immediately. Anyone using it will see a not-found page.')"
                    :confirm-label="__('Generate new link')"
                    confirm-action="generate"
                >
                    <x-slot name="trigger">
                        <flux:button variant="ghost" icon="arrow-path" data-test="share-regenerate">{{ __('Generate new link') }}</flux:button>
                    </x-slot>
                </x-confirm-modal>

                <span class="text-sm {{ $this->link->is_active ? 'text-green-700' : 'text-subtle' }}" data-test="share-status">
                    {{ $this->link->is_active ? __('Active') : __('Inactive') }}
                </span>
            </div>
        @endif
    </div>
</x-panel>
