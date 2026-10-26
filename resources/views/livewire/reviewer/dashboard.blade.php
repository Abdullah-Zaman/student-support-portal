@php
    $counts = $this->counts;
    $user = auth()->user();
    $scope = $user->hasRole(\App\Enums\RoleName::Dean->value) ? $user->faculty?->name : $user->department?->name;
@endphp

<div class="flex flex-col gap-6 py-7">
    <x-page-header
        :title="__('Welcome, :name', ['name' => explode(' ', $user->name)[0]])"
        :subtitle="__('Applications forwarded to you for review · :scope', ['scope' => $scope])"
    >
        <x-slot:actions>
            <flux:button :href="route($this->routePrefix().'applications.index')" variant="primary" icon="inbox-arrow-down" wire:navigate>
                {{ __('Forwarded to me') }}
            </flux:button>
        </x-slot:actions>
    </x-page-header>

    <section aria-label="{{ __('Summary') }}" class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
        <x-stat-tile :label="__('Forwarded to me')" :value="$counts['forwarded']" />
        <x-stat-tile :label="__('Awaiting my remark')" :value="$counts['awaiting']" :urgent="$counts['awaiting'] > 0" />
        <x-stat-tile :label="__('Remarked')" :value="$counts['remarked']" />
        <x-stat-tile :label="__('Forwarded onward')" :value="$counts['onward']" />
    </section>

    <x-panel :title="__('Recently forwarded')">
        <x-slot:actions>
            <flux:link :href="route($this->routePrefix().'applications.index')" class="text-sm font-semibold" wire:navigate>{{ __('View all') }}</flux:link>
        </x-slot:actions>

        @forelse ($this->recent as $application)
            <a
                href="{{ route($this->routePrefix().'applications.show', $application) }}"
                wire:navigate
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 no-underline first:border-t-0"
            >
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="truncate font-semibold text-ink">{{ $application->subject }}</span>
                    <span class="text-[13px] text-subtle tabular-nums">{{ $application->application_no }} · {{ $application->student->user->name }} · {{ $application->category->name }}</span>
                </span>
                <x-status-badge :status="$application->status" />
            </a>
        @empty
            <div class="px-[22px] py-10 text-center text-sm text-subtle">{{ __('Nothing has been forwarded to you yet.') }}</div>
        @endforelse
    </x-panel>
</div>
