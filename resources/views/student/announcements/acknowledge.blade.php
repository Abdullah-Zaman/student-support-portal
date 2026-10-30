<x-layouts::student :title="__('Important announcement')">
    <div class="mx-auto flex max-w-2xl flex-col gap-6 py-8">
        <x-page-header :title="__('Important announcement')" :subtitle="__('Please read this announcement to continue using the portal.')" />

        <x-card>
            <div class="flex flex-col gap-4">
                <h2 class="m-0 text-[17px] font-bold text-ink">{{ $announcement->title }}</h2>

                @if ($announcement->image_path)
                    <img src="{{ route('announcements.image.show', $announcement) }}" alt="" class="w-full max-w-md rounded-[10px] border border-border-soft">
                @endif

                <p class="m-0 text-[15px] leading-relaxed whitespace-pre-line text-ink-soft">{{ $announcement->body }}</p>

                @if ($announcement->attachment_path)
                    <flux:button :href="route('announcements.attachment.show', $announcement)" variant="ghost" size="sm" icon="paper-clip" class="self-start">
                        {{ $announcement->attachment_original_name ?? __('Download attachment') }}
                    </flux:button>
                @endif

                @if ($announcement->survey_url)
                    <flux:button :href="$announcement->survey_url" target="_blank" rel="noopener noreferrer" variant="filled" icon="arrow-top-right-on-square" class="self-start">
                        {{ __('Open the form') }}
                    </flux:button>
                @endif

                <form method="POST" action="{{ route('student.announcements.acknowledge.store', $announcement) }}" class="flex flex-col gap-4 border-t border-border-soft pt-4">
                    @csrf

                    @if ($announcement->survey_url)
                        <div>
                            <flux:checkbox name="survey_marked_done" value="1" :label="__('I have filled the form')" />
                            @error('survey_marked_done')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <flux:button type="submit" variant="primary" class="self-start">{{ __('I have read this') }}</flux:button>
                </form>
            </div>
        </x-card>
    </div>
</x-layouts::student>
