<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Contact Super Admin')" :subtitle="__('Report a problem with your account, a student record or the system. Only you and the Super Admin can see your requests.')">
        <x-slot:actions>
            <flux:button :href="route('support.create')" variant="primary" icon="plus" wire:navigate data-test="new-support-request">{{ __('New request') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($this->requests->isEmpty())
        <x-empty-state icon="lifebuoy" :title="__('No requests yet')" :description="__('When you report a problem it appears here with its status.')" />
    @else
        <x-panel :title="__('Your requests')">
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($this->requests as $request)
                    <li class="border-t border-border-soft first:border-t-0" wire:key="support-{{ $request->id }}">
                        <a href="{{ route('support.show', $request) }}" wire:navigate class="flex flex-wrap items-start gap-x-4 gap-y-1.5 px-[22px] py-3.5 no-underline hover:bg-page">
                            <span class="flex min-w-0 flex-1 basis-72 flex-col gap-0.5">
                                <span class="truncate font-semibold text-ink">{{ $request->subject }}</span>
                                <span class="text-[13px] text-subtle">{{ __($request->category->label()) }} · {{ $request->created_at->format('j M Y, g:i A') }}</span>
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
