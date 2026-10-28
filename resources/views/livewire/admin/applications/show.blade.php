@php
    $routePrefix = auth()->user()->hasRole(\App\Enums\RoleName::SuperAdmin->value) ? 'super-admin.' : 'admin.';
@endphp

<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="$application->subject" :subtitle="$application->application_no">
        <x-slot:actions>
            <x-priority-badge :priority="$application->priority" />
            <x-status-badge :status="$application->status" />
            <x-forwarded-badge :target="$application->forwarded_to" class="py-1! text-[13px]!" />
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-start gap-6">
        <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-6">
            <x-panel :title="__('Student')">
                <dl class="m-0 flex flex-wrap gap-x-10 gap-y-4 px-[22px] py-5">
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Name') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">
                            <flux:link :href="route($routePrefix.'students.show', $application->student)" wire:navigate>
                                {{ $application->student->user->name }}
                            </flux:link>
                        </dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Registration no.') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold tabular-nums">{{ $application->student->registration_no }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Program') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->student->program->name }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Batch') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->student->batch_label }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Current semester') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->student->semesterLabel() }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Semester at submission') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->semester_at_submission }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Email') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->student->user->email }}</dd>
                    </div>
                </dl>
            </x-panel>

            @if ($this->previousApplications->isNotEmpty())
                <x-panel :title="__('Previous applications by this student')">
                    <ul class="m-0 flex flex-col">
                        @foreach ($this->previousApplications as $previous)
                            <li class="flex items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                                <a href="{{ route($routePrefix.'applications.show', $previous) }}" wire:navigate class="flex flex-col gap-0.5 no-underline">
                                    <span class="font-semibold text-ink">{{ $previous->application_no }} &middot; {{ $previous->category->name }}</span>
                                    <span class="text-[13px] text-subtle">{{ $previous->created_at->format('j M Y') }}</span>
                                </a>
                                <x-status-badge :status="$previous->status" />
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif

            <x-card>
                <dl class="m-0 flex flex-wrap gap-x-10 gap-y-4 pb-5">
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Category') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->category->name }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Submitted on') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->created_at->format('j F Y, g:i A') }}</dd>
                    </div>
                </dl>

                <flux:separator />

                <p class="m-0 pt-5 text-[15px] leading-relaxed whitespace-pre-line text-ink-soft">{{ $application->body }}</p>

                @if ($application->status === \App\Enums\ApplicationStatus::Resolved && $application->resolution_note)
                    <div class="mt-5 rounded-[10px] border border-[#CBE6D3] bg-[#E2F3E8] px-4 py-3.5">
                        <span class="block text-xs font-semibold tracking-[0.06em] text-[#1F6A3A] uppercase">{{ __('Resolution note') }}</span>
                        <p class="m-0 mt-1 text-sm leading-relaxed whitespace-pre-line text-[#1F6A3A]">{{ $application->resolution_note }}</p>
                    </div>
                @endif

                @if ($application->status === \App\Enums\ApplicationStatus::Rejected && $application->rejection_reason)
                    <div class="mt-5 rounded-[10px] border border-[#F3C4C0] bg-[#FCEBEA] px-4 py-3.5">
                        <span class="block text-xs font-semibold tracking-[0.06em] text-[#8A1C14] uppercase">{{ __('Rejection reason') }}</span>
                        <p class="m-0 mt-1 text-sm leading-relaxed whitespace-pre-line text-[#8A1C14]">{{ $application->rejection_reason }}</p>
                    </div>
                @endif
            </x-card>

            @if ($application->attachments->isNotEmpty())
                <x-panel :title="__('Attachments')">
                    <ul class="m-0 flex flex-col">
                        @foreach ($application->attachments as $attachment)
                            <li class="flex items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                                <span class="flex items-center gap-2.5 truncate text-sm text-ink-soft">
                                    <flux:icon icon="paper-clip" variant="micro" class="shrink-0 text-subtle" />
                                    {{ $attachment->original_name }}
                                </span>
                                <flux:button :href="route('applications.attachments.show', $attachment)" variant="ghost" size="sm" icon="arrow-down-tray">
                                    {{ __('Download') }}
                                </flux:button>
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif

            @can('viewCorrespondence', $application)
                <livewire:correspondence.thread :application="$application" />
            @endcan

            <x-panel :title="__('Messages')">
                @if ($this->isFinal)
                    <div class="px-[22px] py-6 text-center text-sm text-subtle">
                        {{ __('This application is :status. No further messages can be sent.', ['status' => \Illuminate\Support\Str::headline($application->status->value)]) }}
                    </div>
                @else
                    <div
                        x-data="{
                            message: @js($message_body),
                            request: @js($request_body),
                        }"
                        class="flex flex-col gap-5 px-[22px] py-5"
                    >
                        <div class="flex flex-col gap-2">
                            <flux:textarea
                                wire:model="message_body"
                                x-model="message"
                                :label="__('Send a message')"
                                rows="3"
                                maxlength="{{ $this->messageMaxLength() }}"
                                :placeholder="__('Write a free-text message to the student.')"
                            />
                            <div class="flex items-center justify-between">
                                <flux:error name="message_body" />
                                <span class="self-end text-xs text-subtle" x-text="message.length + ' / {{ $this->messageMaxLength() }}'"></span>
                            </div>
                            <flux:button variant="primary" class="self-end" wire:click="sendMessage">{{ __('Send message') }}</flux:button>
                        </div>

                        <flux:separator />

                        @if ($this->hasOpenRequest)
                            <div class="rounded-[10px] border border-[#F1D9A6] bg-[#FFF7E8] px-4 py-3.5 text-sm text-[#6E4A10]">
                                {{ __('Waiting for the student to respond to an open request.') }}
                            </div>
                        @else
                            <div class="flex flex-col gap-2">
                                <flux:textarea
                                    wire:model="request_body"
                                    x-model="request"
                                    :label="__('Request information or a document')"
                                    rows="3"
                                    maxlength="{{ $this->messageMaxLength() }}"
                                    :placeholder="__('What do you need from the student?')"
                                />
                                <div class="flex items-center justify-between">
                                    <flux:error name="request_body" />
                                    <span class="self-end text-xs text-subtle" x-text="request.length + ' / {{ $this->messageMaxLength() }}'"></span>
                                </div>

                                <div class="flex flex-wrap justify-end gap-2">
                                    <x-confirm-modal
                                        name="confirm-info-request"
                                        :heading="__('Send an info request?')"
                                        :text="__('The application moves to Info Required until the student responds.')"
                                        :confirmLabel="__('Send info request')"
                                        variant="primary"
                                        confirmAction="sendInfoRequest"
                                    >
                                        <x-slot:trigger>
                                            <flux:button variant="primary">{{ __('Request info') }}</flux:button>
                                        </x-slot:trigger>
                                    </x-confirm-modal>

                                    <x-confirm-modal
                                        name="confirm-document-request"
                                        :heading="__('Send a document request?')"
                                        :text="__('The application moves to Info Required until the student responds.')"
                                        :confirmLabel="__('Send document request')"
                                        variant="primary"
                                        confirmAction="sendDocumentRequest"
                                    >
                                        <x-slot:trigger>
                                            <flux:button variant="primary">{{ __('Request document') }}</flux:button>
                                        </x-slot:trigger>
                                    </x-confirm-modal>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </x-panel>

            <x-panel :title="__('Internal notes')">
                <x-slot:actions>
                    <span class="text-xs text-subtle">{{ __('Never visible to the student') }}</span>
                </x-slot:actions>

                <div class="flex flex-col">
                    @forelse ($this->internalNotes as $note)
                        <div class="border-t border-border-soft px-[22px] py-4 first:border-t-0">
                            @if ($editing_note_id === $note->id)
                                <div class="flex flex-col gap-2.5">
                                    <flux:textarea wire:model="editing_note_body" rows="3" />
                                    <div class="flex gap-2">
                                        <flux:button size="sm" variant="primary" wire:click="saveNote">{{ __('Save') }}</flux:button>
                                        <flux:button size="sm" variant="ghost" wire:click="cancelEditingNote">{{ __('Cancel') }}</flux:button>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex flex-col gap-1">
                                        <span class="text-[13px] font-semibold text-subtle">{{ $note->admin->name }} &middot; {{ $note->created_at->format('j M Y, g:i A') }}</span>
                                        <p class="m-0 text-sm leading-relaxed whitespace-pre-line text-ink-soft">{{ $note->body }}</p>
                                    </div>
                                    @can('update', $note)
                                        <flux:button size="sm" variant="ghost" icon="pencil" wire:click="startEditingNote({{ $note->id }})" />
                                    @endcan
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No internal notes yet.') }}</div>
                    @endforelse
                </div>

                <div class="flex flex-col gap-2.5 border-t border-border-soft px-[22px] py-4">
                    <flux:textarea wire:model="new_note" :label="__('Add a note')" rows="3" :placeholder="__('Visible only to Admin Officers and Super Admin.')" />
                    <flux:button variant="primary" class="self-end" wire:click="addNote">{{ __('Add note') }}</flux:button>
                </div>
            </x-panel>
        </div>

        <aside class="flex min-w-0 flex-1 basis-80 flex-col gap-6">
            @if (! $this->isFinal && $this->forwardOptions !== [])
                <x-panel :title="__('Forward')">
                    <x-forward-form :options="$this->forwardOptions" />
                </x-panel>
            @endif

            @if ($this->isFinal)
                <x-panel :title="__('Actions')">
                    <div class="px-[22px] py-5 text-sm text-subtle">
                        {{ __('This application is :status and cannot be changed further.', ['status' => \Illuminate\Support\Str::headline($application->status->value)]) }}
                    </div>
                </x-panel>
            @elseif ($this->isResolved)
                <x-panel :title="__('Close')">
                    <div class="flex flex-col gap-3 px-[22px] py-4">
                        <p class="m-0 text-sm text-subtle">{{ __('This application has been resolved. Closing it is final.') }}</p>

                        <x-confirm-modal
                            name="confirm-close"
                            :heading="__('Close this application?')"
                            :text="__('This is final and cannot be undone.')"
                            :confirmLabel="__('Close')"
                            variant="primary"
                            confirmAction="close"
                        >
                            <x-slot:trigger>
                                <flux:button variant="primary" class="w-full">{{ __('Close application') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    </div>
                </x-panel>
            @else
                <x-panel :title="__('Priority')">
                    <div class="flex flex-col gap-3 px-[22px] py-4">
                        <flux:select wire:model="priority_input" :label="__('Priority')">
                            @foreach (\App\Enums\ApplicationPriority::cases() as $priority)
                                <flux:select.option :value="$priority->value">{{ \Illuminate\Support\Str::headline($priority->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <x-confirm-modal
                            name="confirm-update-priority"
                            :heading="__('Change priority?')"
                            :text="__('This updates the queue priority. Students never see priority.')"
                            :confirmLabel="__('Change priority')"
                            variant="primary"
                            confirmAction="updatePriority"
                        >
                            <x-slot:trigger>
                                <flux:button variant="primary" class="w-full">{{ __('Update priority') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    </div>
                </x-panel>

                <x-panel :title="__('Status')">
                    <div class="flex flex-col gap-3 px-[22px] py-4">
                        <flux:select wire:model="status_input" :label="__('New status')" :placeholder="__('Choose a status')">
                            <flux:select.option value="submitted">{{ __('Submitted') }}</flux:select.option>
                            <flux:select.option value="under_review">{{ __('Under Review') }}</flux:select.option>
                            <flux:select.option value="info_required">{{ __('Info Required') }}</flux:select.option>
                            <flux:select.option value="in_progress">{{ __('In Progress') }}</flux:select.option>
                        </flux:select>
                        <flux:error name="status_input" />

                        <x-confirm-modal
                            name="confirm-update-status"
                            :heading="__('Change status?')"
                            :text="__('The student will see this change in their timeline.')"
                            :confirmLabel="__('Change status')"
                            variant="primary"
                            confirmAction="updateStatus"
                        >
                            <x-slot:trigger>
                                <flux:button variant="primary" class="w-full">{{ __('Update status') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    </div>
                </x-panel>

                <x-panel :title="__('Resolve')">
                    <div class="flex flex-col gap-3 px-[22px] py-4">
                        <flux:textarea wire:model="resolution_note" :label="__('Resolution note')" rows="3" :placeholder="__('What was done to resolve this?')" />

                        <x-confirm-modal
                            name="confirm-resolve"
                            :heading="__('Mark this application resolved?')"
                            :text="__('The student will see the resolution note.')"
                            :confirmLabel="__('Resolve')"
                            variant="primary"
                            confirmAction="resolve"
                        >
                            <x-slot:trigger>
                                <flux:button variant="primary" class="w-full">{{ __('Resolve application') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    </div>
                </x-panel>

                <x-panel :title="__('Reject')">
                    <div class="flex flex-col gap-3 px-[22px] py-4">
                        <flux:textarea wire:model="rejection_reason" :label="__('Rejection reason')" rows="3" :placeholder="__('Why is this application being rejected?')" />

                        <x-confirm-modal
                            name="confirm-reject"
                            :heading="__('Reject this application?')"
                            :text="__('This is final. The student will see the rejection reason.')"
                            :confirmLabel="__('Reject')"
                            variant="danger"
                            confirmAction="reject"
                        >
                            <x-slot:trigger>
                                <flux:button variant="danger" class="w-full">{{ __('Reject application') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    </div>
                </x-panel>

                <x-panel :title="__('Close')">
                    <div class="flex flex-col gap-3 px-[22px] py-4">
                        <p class="m-0 text-sm text-subtle">{{ __('Close this application without resolving or rejecting it. This is final.') }}</p>

                        <x-confirm-modal
                            name="confirm-close"
                            :heading="__('Close this application?')"
                            :text="__('This is final and cannot be undone.')"
                            :confirmLabel="__('Close')"
                            variant="primary"
                            confirmAction="close"
                        >
                            <x-slot:trigger>
                                <flux:button variant="primary" class="w-full">{{ __('Close application') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    </div>
                </x-panel>
            @endif

            <x-panel :title="__('Timeline')">
                @if ($this->timeline->isEmpty())
                    <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No activity yet.') }}</div>
                @else
                    <div class="flex flex-col">
                        @foreach ($this->timeline as $item)
                            @if ($item instanceof \App\Models\ApplicationEvent)
                                <div class="flex gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                                    <span class="mt-1.5 size-2 shrink-0 rounded-full bg-plum"></span>
                                    <div class="flex flex-col gap-0.5">
                                        <span class="text-sm leading-relaxed text-ink-soft">{{ $item->timelineDescription() }}</span>
                                        <span class="text-[13px] text-subtle">{{ $item->created_at->format('j M Y, g:i A') }}</span>
                                    </div>
                                </div>
                            @else
                                <x-message-timeline-item :message="$item" />
                            @endif
                        @endforeach
                    </div>
                @endif
            </x-panel>
        </aside>
    </div>
</div>
