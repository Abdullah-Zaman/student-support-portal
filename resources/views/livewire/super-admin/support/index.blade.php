<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Support requests')" :subtitle="__('Problems reported by Admin Officers, HODs and Deans. Open requests first.')" />

    <div class="flex flex-wrap items-end gap-3">
        <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
            <flux:select wire:model.live="status" :label="__('Status')">
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach (\App\Enums\SupportRequestStatus::cases() as $status)
                    <flux:select.option :value="$status->value">{{ __($status->label()) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="flex min-w-0 flex-1 basis-40 flex-col gap-1">
            <flux:select wire:model.live="category" :label="__('Category')">
                <flux:select.option value="">{{ __('All categories') }}</flux:select.option>
                @foreach (\App\Enums\SupportRequestCategory::cases() as $category)
                    <flux:select.option :value="$category->value">{{ __($category->label()) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($status !== '' || $category !== '')
            <flux:button variant="ghost" wire:click="clearFilters">{{ __('Clear filters') }}</flux:button>
        @endif
    </div>

    @if ($this->requests->isEmpty())
        <x-empty-state icon="lifebuoy" :title="__('No support requests')" :description="__('Try a different filter.')" />
    @else
        <x-panel :title="__('Requests')">
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($this->requests as $request)
                    <li class="border-t border-border-soft first:border-t-0" wire:key="support-{{ $request->id }}">
                        <a href="{{ route('super-admin.support.show', $request) }}" wire:navigate class="flex flex-wrap items-start gap-x-4 gap-y-1.5 px-[22px] py-3.5 no-underline hover:bg-page">
                            <span class="flex min-w-0 flex-1 basis-72 flex-col gap-0.5">
                                <span class="truncate font-semibold text-ink">{{ $request->subject }}</span>
                                <span class="text-[13px] text-subtle">{{ $request->reporter->name }} · {{ __($request->category->label()) }} · {{ $request->created_at->format('j M Y, g:i A') }}</span>
                            </span>
                            <flux:badge :color="$request->status->color()" :icon="$request->status->icon()" size="sm">{{ __($request->status->label()) }}</flux:badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        </x-panel>

        <div>{{ $this->requests->links() }}</div>
    @endif
</div>
