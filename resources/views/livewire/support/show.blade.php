<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="$supportRequest->subject" :subtitle="__($supportRequest->category->label()).' · '.$supportRequest->created_at->format('j M Y, g:i A')">
        <x-slot:actions>
            <flux:badge :color="$supportRequest->status->color()" :icon="$supportRequest->status->icon()" data-test="support-status">{{ __($supportRequest->status->label()) }}</flux:badge>
            <flux:button :href="route('support.index')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Back') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :title="__('Your message')">
        <p class="m-0 px-[22px] py-4 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ $supportRequest->message }}</p>

        @if ($supportRequest->application || $supportRequest->student)
            <p class="m-0 border-t border-border-soft px-[22px] py-3 text-[13px] text-subtle">
                @if ($supportRequest->application)
                    {{ __('Application') }}: {{ $supportRequest->application->application_no }}
                @endif
                @if ($supportRequest->student)
                    {{ __('Student') }}: {{ $supportRequest->student->registration_no }}
                @endif
            </p>
        @endif

        @if ($supportRequest->attachments->isNotEmpty())
            <ul class="m-0 flex list-none flex-wrap gap-2 border-t border-border-soft px-[22px] py-3">
                @foreach ($supportRequest->attachments as $attachment)
                    <li>
                        <flux:button size="sm" variant="ghost" icon="paper-clip" :href="route('support.attachments.show', $attachment)">{{ $attachment->original_name }}</flux:button>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-panel>

    <x-panel :title="__('Super Admin\'s reply')">
        @if (filled($supportRequest->resolution_note))
            <p class="m-0 px-[22px] py-4 text-sm leading-relaxed whitespace-pre-line text-ink-soft" data-test="support-resolution-note">{{ $supportRequest->resolution_note }}</p>
            @if ($supportRequest->resolved_at)
                <p class="m-0 border-t border-border-soft px-[22px] py-3 text-[13px] text-subtle">{{ __('Resolved :time', ['time' => $supportRequest->resolved_at->format('j M Y, g:i A')]) }}</p>
            @endif
        @else
            <p class="m-0 px-[22px] py-4 text-sm text-subtle">{{ __('No reply yet. You will be notified when the Super Admin responds.') }}</p>
        @endif
    </x-panel>
</div>
