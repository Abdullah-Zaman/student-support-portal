@php
    $firstName = explode(' ', auth()->user()->name)[0];
    $hour = now()->hour;
    $greeting = match (true) {
        $hour < 12 => __('Good morning'),
        $hour < 17 => __('Good afternoon'),
        default => __('Good evening'),
    };
@endphp

<x-layouts::student :title="__('Student Dashboard')">
    <div class="flex flex-col gap-6 py-8">
        <x-page-header :title="__(':greeting, :name', ['greeting' => $greeting, 'name' => $firstName])" :subtitle="__('Here is where your applications stand today.')">
            <x-slot:actions>
                <flux:button :href="route('student.applications.create')" variant="primary" icon="plus" wire:navigate>
                    {{ __('New application') }}
                </flux:button>
            </x-slot:actions>
        </x-page-header>

        <section aria-label="{{ __('Your profile') }}" class="flex flex-wrap items-center gap-x-10 gap-y-5 rounded-[14px] border border-border bg-white px-6 py-5">
            <div class="flex flex-1 basis-64 items-center gap-3.5">
                <flux:avatar size="lg" :name="auth()->user()->name" :initials="auth()->user()->initials()" class="bg-avatar! text-plum-link!" />
                <div class="flex flex-col gap-0.5">
                    <span class="text-[17px] font-bold text-ink">{{ auth()->user()->name }}</span>
                    <span class="text-sm text-muted">{{ auth()->user()->student?->program?->name }}</span>
                </div>
            </div>

            <dl class="m-0 flex flex-wrap gap-x-10 gap-y-4">
                <div class="flex flex-col gap-1">
                    <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Registration no.') }}</dt>
                    <dd class="m-0 text-[15px] font-semibold tabular-nums">{{ auth()->user()->student?->registration_no }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Semester') }}</dt>
                    <dd class="m-0 text-[15px] font-semibold">{{ auth()->user()->student?->current_semester }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Batch') }}</dt>
                    <dd class="m-0 text-[15px] font-semibold">{{ auth()->user()->student?->batch_label }}</dd>
                </div>
                <div class="flex flex-col gap-1">
                    <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Email') }}</dt>
                    <dd class="m-0 text-[15px] font-semibold">{{ auth()->user()->email }}</dd>
                </div>
            </dl>
        </section>

        @if ($actionNeeded)
            <section aria-label="{{ __('Action needed') }}" class="flex flex-wrap items-center gap-4 rounded-[14px] border border-[#F1D9A6] bg-[#FFF7E8] px-5.5 py-4.5">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-[10px] bg-[#FBE7BF] text-[#8A5300]">
                    <flux:icon icon="exclamation-circle" variant="outline" class="size-5" />
                </span>
                <div class="flex flex-1 basis-80 flex-col gap-0.5">
                    <span class="text-[15px] font-bold text-[#5C3A00]">{{ __('The Admin Office needs more information from you') }}</span>
                    <span class="text-sm text-[#6E4A10]">{{ $actionNeeded->application_no }} &middot; {{ $actionNeeded->category->name }} — {{ $actionNeeded->subject }}</span>
                </div>
                <flux:button :href="route('student.applications.show', $actionNeeded)" variant="primary" class="bg-[#8A5300]! hover:bg-[#6E4210]!" wire:navigate>
                    {{ __('View application') }}
                </flux:button>
            </section>
        @endif

        <x-timetable-today-card :schedule="$schedule" />

        <section aria-label="{{ __('Summary') }}" class="grid grid-cols-[repeat(auto-fit,minmax(200px,1fr))] gap-4">
            <x-stat-tile :label="__('Total applications')" :value="$stats['total']" />
            <x-stat-tile :label="__('Waiting on you')" :value="$stats['waiting_on_you']" :note="__('Information requested')" />
            <x-stat-tile :label="__('Being processed')" :value="$stats['processing']" :note="__('Under review or in progress')" />
            <x-stat-tile :label="__('Resolved')" :value="$stats['resolved']" />
        </section>

        <div class="flex flex-wrap items-start gap-6">
            <x-panel :title="__('My applications')" class="min-w-0 flex-[999_1_640px]">
                <x-slot:actions>
                    <flux:link :href="route('student.applications.index')" class="text-sm font-semibold" wire:navigate>{{ __('View all') }}</flux:link>
                </x-slot:actions>

                @if ($applications->isEmpty())
                    <div class="px-[22px] py-10 text-center text-sm text-subtle">
                        {{ __("You haven't submitted any applications yet.") }}
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[640px] border-collapse text-sm">
                            <thead>
                                <tr class="text-left text-xs tracking-[0.06em] text-subtle uppercase">
                                    <th class="px-[22px] py-3 font-semibold">{{ __('Application') }}</th>
                                    <th class="px-3 py-3 font-semibold">{{ __('Category') }}</th>
                                    <th class="px-3 py-3 font-semibold">{{ __('Submitted') }}</th>
                                    <th class="px-[22px] py-3 font-semibold">{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($applications as $application)
                                    <tr class="border-t border-border-soft">
                                        <td class="px-[22px] py-3.5">
                                            <a href="{{ route('student.applications.show', $application) }}" wire:navigate class="flex flex-col gap-0.5 no-underline">
                                                <span class="font-semibold text-ink">{{ $application->subject }}</span>
                                                <span class="text-[13px] text-subtle tabular-nums">{{ $application->application_no }}</span>
                                            </a>
                                        </td>
                                        <td class="px-3 py-3.5 text-ink-soft">{{ $application->category->name }}</td>
                                        <td class="px-3 py-3.5 whitespace-nowrap text-ink-soft">{{ $application->created_at->format('j M, g:i A') }}</td>
                                        <td class="px-[22px] py-3.5"><x-status-badge :status="$application->status" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel>

            <aside class="flex min-w-0 flex-1 basis-80 flex-col gap-6">
                <x-panel :title="__('Announcements')">
                    <x-slot:actions>
                        <flux:link :href="Route::has('student.announcements.index') ? route('student.announcements.index') : '#'" class="text-sm font-semibold" wire:navigate>
                            {{ __('See all') }}
                        </flux:link>
                    </x-slot:actions>

                    @forelse ($announcements as $announcement)
                        <a
                            href="{{ Route::has('student.announcements.index') ? route('student.announcements.index') : '#' }}"
                            wire:navigate
                            class="flex flex-col gap-1 border-t border-border-soft px-[22px] py-4 no-underline first:border-t-0"
                        >
                            <span class="flex items-center gap-2 text-[15px] font-semibold text-ink">
                                {{ $announcement->title }}
                                @if ($announcement->isRecentlyPublished())
                                    <flux:badge color="amber" size="sm">{{ __('New') }}</flux:badge>
                                @endif
                            </span>
                            <span class="text-[13px] text-subtle">{{ ($announcement->publish_at ?? $announcement->created_at)->format('j M Y') }}</span>
                        </a>
                    @empty
                        <div class="px-[22px] py-6 text-center text-sm text-subtle">
                            {{ __('No announcements yet.') }}
                        </div>
                    @endforelse
                </x-panel>

                <x-panel :title="__('Latest updates')">
                    @if ($latestEvents->isEmpty())
                        <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No activity yet.') }}</div>
                    @else
                        @foreach ($latestEvents as $event)
                            <div class="flex gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                                <span class="mt-1.5 size-2 shrink-0 rounded-full bg-plum"></span>
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-sm leading-relaxed text-ink-soft">{{ $event->description() }}</span>
                                    <span class="text-[13px] text-subtle">{{ $event->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </x-panel>
            </aside>
        </div>
    </div>
</x-layouts::student>
