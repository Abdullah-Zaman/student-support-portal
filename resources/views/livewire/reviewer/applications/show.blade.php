<div class="flex flex-col gap-6 py-7">
    <div>
        <flux:link :href="route($this->routePrefix().'applications.index')" wire:navigate class="inline-flex items-center gap-1 text-sm font-semibold">
            <flux:icon icon="arrow-left" variant="micro" class="size-4" />
            {{ __('Forwarded to me') }}
        </flux:link>
    </div>

    <x-page-header :title="$application->subject" :subtitle="$application->application_no.' · '.$application->department->name">
        <x-slot:actions>
            <x-status-badge :status="$application->status" />
        </x-slot:actions>
    </x-page-header>

    <div class="flex flex-wrap items-start gap-6">
        <div class="flex min-w-0 flex-[999_1_640px] flex-col gap-6">
            <x-panel :title="__('Student')">
                <dl class="m-0 flex flex-wrap gap-x-10 gap-y-4 px-[22px] py-5">
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Name') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->student->user->name }}</dd>
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
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Semester at submission') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->semester_at_submission }}</dd>
                    </div>
                    <div class="flex flex-col gap-1">
                        <dt class="text-xs font-semibold tracking-[0.06em] text-subtle uppercase">{{ __('Email') }}</dt>
                        <dd class="m-0 text-[15px] font-semibold">{{ $application->student->user->email }}</dd>
                    </div>
                </dl>
            </x-panel>

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

            <x-panel :title="__('Forwards & remarks')">
                <x-forward-chain :forwards="$this->forwards" />
            </x-panel>
        </div>

        <aside class="flex min-w-0 flex-1 basis-80 flex-col gap-6">
            @if ($application->isFinal())
                <x-panel :title="__('Actions')">
                    <div class="px-[22px] py-5 text-sm text-subtle">
                        {{ __('This application is :status. No more remarks or forwards.', ['status' => \Illuminate\Support\Str::headline($application->status->value)]) }}
                    </div>
                </x-panel>
            @else
                @can('remark', $application)
                    <x-panel :title="__('Add a remark')">
                        <form wire:submit="addRemark" class="flex flex-col gap-3 px-[22px] py-4">
                            <flux:radio.group wire:model.live="remark_type" :label="__('Your remark')">
                                @foreach (\App\Enums\RemarkType::cases() as $type)
                                    <flux:radio :value="$type->value" :label="__($type->label())" />
                                @endforeach
                            </flux:radio.group>

                            <flux:textarea
                                wire:model="remark_body"
                                :label="$remark_type === \App\Enums\RemarkType::Comment->value ? __('Comment') : __('Comment (optional)')"
                                rows="3"
                                maxlength="1000"
                            />

                            <flux:button type="submit" variant="primary" class="w-full">{{ __('Save remark') }}</flux:button>
                        </form>
                    </x-panel>
                @endcan

                @if ($this->forwardOptions !== [])
                    <x-panel :title="__('Forward onward')">
                        <x-forward-form :options="$this->forwardOptions" />
                    </x-panel>
                @endif
            @endif
        </aside>
    </div>
</div>
