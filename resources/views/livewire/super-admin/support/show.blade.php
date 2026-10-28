<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="$supportRequest->subject" :subtitle="__($supportRequest->category->label()).' · '.$supportRequest->created_at->format('j M Y, g:i A')">
        <x-slot:actions>
            <flux:badge :color="$supportRequest->status->color()" :icon="$supportRequest->status->icon()" data-test="support-status">{{ __($supportRequest->status->label()) }}</flux:badge>
            <flux:button :href="route('super-admin.support.index')" variant="ghost" icon="arrow-left" wire:navigate>{{ __('Back') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-start gap-6">
        <div class="flex min-w-0 flex-1 basis-96 flex-col gap-6">
            <x-panel :title="__('Message')">
                <p class="m-0 px-[22px] py-4 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ $supportRequest->message }}</p>

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

            <x-panel :title="__('Reply and resolution')">
                <div class="flex flex-col gap-3 px-[22px] py-4">
                    @if ($supportRequest->isResolved())
                        <p class="m-0 text-sm leading-relaxed whitespace-pre-line text-ink-soft" data-test="support-resolution-note">{{ $supportRequest->resolution_note }}</p>
                        <p class="m-0 text-[13px] text-subtle">{{ __('Resolved by :name on :time', ['name' => $supportRequest->resolver?->name, 'time' => $supportRequest->resolved_at?->format('j M Y, g:i A')]) }}</p>
                    @else
                        <flux:textarea wire:model="note" :label="__('Reply / resolution note')" rows="4" maxlength="2000" :description="__('The reporter sees this note and is told by email.')" />

                        <div class="flex flex-wrap justify-end gap-2">
                            @if ($supportRequest->status === \App\Enums\SupportRequestStatus::Open)
                                <flux:button wire:click="markInProgress" wire:loading.attr="disabled" wire:target="markInProgress" icon="arrow-path" data-test="support-in-progress">{{ __('Set in progress') }}</flux:button>
                            @endif

                            <flux:button wire:click="reply" wire:loading.attr="disabled" wire:target="reply" icon="chat-bubble-left" data-test="support-reply">{{ __('Send reply') }}</flux:button>

                            <x-confirm-modal
                                name="confirm-resolve"
                                :heading="__('Resolve this request?')"
                                :text="__('The reporter is told it is resolved, with your note. A resolved request cannot be changed.')"
                                :confirmLabel="__('Resolve')"
                                variant="primary"
                                confirmAction="resolve"
                            >
                                <x-slot:trigger>
                                    <flux:button variant="primary" icon="check-circle" data-test="support-resolve">{{ __('Resolve') }}</flux:button>
                                </x-slot:trigger>
                            </x-confirm-modal>
                        </div>
                    @endif
                </div>
            </x-panel>
        </div>

        <aside class="flex min-w-0 flex-1 basis-72 flex-col gap-6">
            <x-panel :title="__('Reporter')">
                <div class="flex flex-col gap-1 px-[22px] py-4 text-sm">
                    <span class="font-semibold text-ink">{{ $supportRequest->reporter->name }}</span>
                    <span class="text-subtle">{{ \Illuminate\Support\Str::headline((string) $supportRequest->reporter->staffRole()?->value) }}</span>
                    <span class="text-subtle">{{ $supportRequest->reporter->email }}</span>
                </div>
            </x-panel>

            @if ($supportRequest->application || $supportRequest->student)
                <x-panel :title="__('Related')">
                    <div class="flex flex-col gap-2 px-[22px] py-4 text-sm">
                        @if ($supportRequest->application)
                            <a href="{{ route('super-admin.applications.show', $supportRequest->application) }}" wire:navigate class="font-semibold text-plum-link" data-test="support-related-application">{{ $supportRequest->application->application_no }}</a>
                        @endif
                        @if ($supportRequest->student)
                            <a href="{{ route('super-admin.students.show', $supportRequest->student) }}" wire:navigate class="font-semibold text-plum-link" data-test="support-related-student">{{ $supportRequest->student->registration_no }}</a>
                        @endif
                    </div>
                </x-panel>
            @endif
        </aside>
    </div>
</div>
