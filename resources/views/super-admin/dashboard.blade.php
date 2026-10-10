<x-layouts::admin :title="__('Super Admin Dashboard')">
    <div class="flex flex-col gap-6 py-7">
        <x-page-header :title="__('System overview')" :subtitle="__('All departments · :date', ['date' => now()->format('l, j F Y')])" />

        <section aria-label="{{ __('Summary') }}" class="grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-3.5">
            <x-stat-tile :href="route('super-admin.students.index')" :label="__('Departments')" :value="$stats['departments']" />
            <x-stat-tile :href="route('super-admin.admins.index')" :label="__('Admin Officers')" :value="$stats['admin_officers']" :note="__('Across all departments')" />
            <x-stat-tile :href="route('super-admin.students.index')" :label="__('Students')" :value="$stats['students']" :note="__('Registered accounts')" />
            <x-stat-tile :href="route('super-admin.applications.index')" :label="__('All applications')" :value="$stats['applications']" :note="__('All time')" />
        </section>

        <div class="flex flex-wrap items-start gap-6">
            <x-panel :title="__('Recent activity')" class="min-w-0 flex-[999_1_640px]">
                @forelse ($recentActivity as $event)
                    <div class="flex gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-plum"></span>
                        <div class="flex flex-col gap-0.5">
                            <span class="text-sm leading-relaxed text-ink-soft">{{ $event->description() }}</span>
                            <span class="text-[13px] text-subtle">{{ $event->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                @empty
                    <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No recent activity.') }}</div>
                @endforelse
            </x-panel>

            <aside class="flex min-w-0 flex-1 basis-72 flex-col gap-6">
                @if ($recentAnnouncements->isEmpty())
                    <x-empty-state
                        icon="megaphone"
                        :title="__('No announcements yet')"
                        :description="__('Announcements from every department will appear here.')"
                    />
                @else
                    <x-panel :title="__('Recent announcements')">
                        @foreach ($recentAnnouncements as $announcement)
                            <div class="flex flex-col gap-0.5 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                                <span class="text-sm font-semibold text-ink">{{ $announcement->title }}</span>
                                <span class="text-[13px] text-subtle">{{ $announcement->department->name }} &middot; {{ $announcement->created_at->diffForHumans() }}</span>
                            </div>
                        @endforeach
                    </x-panel>
                @endif
            </aside>
        </div>
    </div>
</x-layouts::admin>
