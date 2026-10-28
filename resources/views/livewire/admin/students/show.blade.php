@php
    $routePrefix = $this->isSuperAdmin ? 'super-admin.' : 'admin.';
@endphp

<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="$student->user->name" :subtitle="$student->registration_no">
        <x-slot:actions>
            <flux:badge :color="$this->isSuspended ? 'red' : 'green'">
                {{ $this->isSuspended ? __('Suspended') : __('Active') }}
            </flux:badge>
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-start gap-6">
        <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-6">
            <x-card>
                <dl class="m-0 flex flex-wrap gap-x-10 gap-y-4">
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Program') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $student->program->name }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Batch') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $student->batch_label }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Current semester') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $student->semesterLabel() }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Email') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $student->user->email }}</dd>
                    </div>
                </dl>
            </x-card>

            <x-panel :title="__('Applications')">
                @if ($this->applications->isEmpty())
                    <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No applications yet.') }}</div>
                @else
                    <ul class="m-0 flex flex-col">
                        @foreach ($this->applications as $application)
                            <li class="flex items-center justify-between gap-3 border-t border-border-soft px-[22px] py-3.5 first:border-t-0">
                                <a href="{{ route($routePrefix.'applications.show', $application) }}" wire:navigate class="flex flex-col gap-0.5 no-underline">
                                    <span class="font-semibold text-ink">{{ $application->subject }}</span>
                                    <span class="text-[13px] text-subtle tabular-nums">{{ $application->application_no }} &middot; {{ $application->category->name }} &middot; {{ $application->created_at->format('j M Y') }}</span>
                                </a>
                                <x-status-badge :status="$application->status" />
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-panel>
        </div>

        <aside class="flex min-w-0 flex-1 basis-80 flex-col gap-6">
            <x-panel :title="__('Correct name')">
                <div class="flex flex-col gap-3 px-[22px] py-4">
                    <flux:input wire:model="name" :label="__('Full name')" />
                    <flux:button variant="primary" class="self-end" wire:click="updateName">{{ __('Save') }}</flux:button>
                </div>
            </x-panel>

            @if ($this->isSuperAdmin)
                <x-panel :title="__('Edit profile')">
                    <div class="flex flex-col gap-3 px-[22px] py-4">
                        <flux:select wire:model="program_id" :label="__('Program')">
                            @foreach ($this->programs as $programOption)
                                <flux:select.option :value="(string) $programOption->id" wire:key="program-{{ $programOption->id }}">{{ $programOption->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        @unless ($student->is_alumni)
                            <flux:select wire:model="current_semester" :label="__('Current semester')">
                                @foreach (range(1, 8) as $semester)
                                    <flux:select.option :value="$semester">{{ $semester }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endunless
                        <flux:input wire:model="batch" :label="__('Batch')" placeholder="F22" />
                        <flux:button variant="primary" class="self-end" wire:click="updateProfile">{{ __('Save') }}</flux:button>
                    </div>
                </x-panel>
            @endif

            <x-panel :title="__('Account')">
                <div class="flex flex-col gap-3 px-[22px] py-4">
                    @can('markEmailVerified', $student)
                        <x-confirm-modal
                            name="confirm-mark-verified"
                            :heading="__('Mark this email as verified?')"
                            :text="__('Use this when the student cannot open the verification email, for example an old mailbox that no longer exists. They will be able to log in.')"
                            :confirmLabel="__('Mark verified')"
                            variant="primary"
                            confirmAction="markEmailVerified"
                        >
                            <x-slot:trigger>
                                <flux:button class="w-full" icon="check-badge" data-test="mark-email-verified">{{ __('Mark email verified') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    @endcan

                    @if ($this->isSuspended)
                        <x-confirm-modal
                            name="confirm-reactivate-student"
                            :heading="__('Reactivate this student?')"
                            :text="__('They will be able to log in again.')"
                            :confirmLabel="__('Reactivate')"
                            variant="primary"
                            confirmAction="reactivate"
                        >
                            <x-slot:trigger>
                                <flux:button variant="primary" class="w-full">{{ __('Reactivate student') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    @else
                        <x-confirm-modal
                            name="confirm-suspend-student"
                            :heading="__('Suspend this student?')"
                            :text="__('They will be logged out immediately and unable to log in until reactivated.')"
                            :confirmLabel="__('Suspend')"
                            variant="danger"
                            confirmAction="suspend"
                        >
                            <x-slot:trigger>
                                <flux:button variant="danger" class="w-full">{{ __('Suspend student') }}</flux:button>
                            </x-slot:trigger>
                        </x-confirm-modal>
                    @endif
                </div>
            </x-panel>
        </aside>
    </div>
</div>
