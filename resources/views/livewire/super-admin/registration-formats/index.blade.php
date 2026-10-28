<div class="flex flex-col gap-6 py-7">
    <x-page-header :title="__('Additional student registration formats')" :subtitle="__('Let old or passed-out students register with a different email and registration number. Every student email must still end in :domain.', ['domain' => \App\Support\Registration\StudentEmailDomain::DOMAIN])">
        <x-slot:actions>
            <flux:button variant="primary" icon="plus" wire:click="startCreate" data-test="add-format">{{ __('Add format') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :title="__('How patterns work')">
        <div class="flex flex-col gap-1.5 px-[22px] py-4 text-sm text-ink-soft">
            <p class="m-0">{{ __('Write the text students type, with placeholders instead of regular expressions:') }}</p>
            <ul class="m-0 list-disc ps-5">
                <li><code>{DIGITS:1-4}</code> {{ __('1 to 4 digits') }}, <code>{DIGITS:3}</code> {{ __('exactly 3') }}</li>
                <li><code>{LETTERS:2-5}</code> {{ __('2 to 5 letters') }}</li>
                <li><code>{BATCH}</code> {{ __('a batch such as F21 or S23') }}, <code>{PROGRAM}</code> {{ __('a program code') }}</li>
            </ul>
            <p class="m-0">{{ __('Example email pattern: bsem{BATCH}-{DIGITS:3} (the domain is added for you). Example registration number: BSEM{BATCH}-{DIGITS:3}.') }}</p>
        </div>
    </x-panel>

    <x-panel :title="__('Formats')">
        @if ($this->formats->isEmpty())
            <div class="px-[22px] py-6 text-center text-sm text-subtle">{{ __('No additional formats yet. Only the current program-based format is accepted.') }}</div>
        @else
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($this->formats as $format)
                    <li class="flex flex-wrap items-start gap-x-4 gap-y-2 border-t border-border-soft px-[22px] py-3.5 first:border-t-0" wire:key="format-{{ $format->id }}" data-test="format-{{ $format->id }}">
                        <div class="flex min-w-0 flex-1 basis-72 flex-col gap-1">
                            <span class="flex flex-wrap items-center gap-2 font-semibold text-ink">
                                {{ $format->name }}
                                <flux:badge :color="$format->is_active ? 'green' : 'zinc'" size="sm">{{ $format->is_active ? __('Active') : __('Disabled') }}</flux:badge>
                                @if ($format->is_alumni)
                                    <flux:badge color="purple" size="sm">{{ __('Alumni') }}</flux:badge>
                                @endif
                            </span>
                            <span class="text-[13px] text-subtle tabular-nums">{{ __('Email') }}: {{ $format->email_template }}{{ \App\Support\Registration\StudentEmailDomain::DOMAIN }}</span>
                            <span class="text-[13px] text-subtle tabular-nums">{{ __('Registration no.') }}: {{ $format->registration_no_template }}</span>
                            @if ($format->program_code || $format->batch)
                                <span class="text-[13px] text-subtle">{{ collect([$format->program_code, $format->batch])->filter()->join(' · ') }}</span>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <flux:button size="sm" wire:click="startEdit({{ $format->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" wire:click="toggleActive({{ $format->id }})" data-test="toggle-format-{{ $format->id }}">{{ $format->is_active ? __('Disable') : __('Enable') }}</flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-panel>

    <x-panel :title="__('Test an email and registration number')">
        <div class="flex flex-col gap-4 px-[22px] py-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <flux:input wire:model.live.debounce.400ms="test_email" :label="__('Email')" placeholder="name@superior.edu.pk" />
                <flux:input wire:model.live.debounce.400ms="test_registration_no" :label="__('Registration no.')" />
                <flux:select wire:model.live="test_program" :label="__('Program (optional)')">
                    <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                    @foreach ($this->programs as $program)
                        <flux:select.option :value="$program->code">{{ $program->code }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($this->testResult)
                @php($result = $this->testResult)
                <div class="rounded-[10px] bg-page px-4 py-3 text-sm text-ink-soft" data-test="format-test-result">
                    @if (! $result['domainOk'])
                        <p class="m-0 font-semibold text-red-700">{{ __('Rejected: the email must be a plain address ending in :domain.', ['domain' => \App\Support\Registration\StudentEmailDomain::DOMAIN]) }}</p>
                    @elseif ($result['current'])
                        <p class="m-0 font-semibold text-[#1E6B3A]">{{ __('Accepted by the current program-based format.') }}</p>
                    @elseif ($result['rule'])
                        <p class="m-0 font-semibold text-[#1E6B3A]">{{ __('Accepted by ":name".', ['name' => $result['rule']->name]) }}</p>
                        <p class="m-0">{{ __('Program: :program · Batch: :batch', ['program' => $result['program'] ?? __('chosen at registration'), 'batch' => $result['batch']]) }}@if ($result['alumni']) · {{ __('Alumni: no current semester') }}@endif</p>
                    @elseif ($result['inactiveRule'])
                        <p class="m-0 font-semibold text-[#7A4A00]">{{ __('Rejected: only the disabled format ":name" matches.', ['name' => $result['inactiveRule']->name]) }}</p>
                    @else
                        <p class="m-0 font-semibold text-red-700">{{ __('Rejected: no active format accepts this email and registration number together.') }}</p>
                    @endif
                </div>
            @endif
        </div>
    </x-panel>

    <flux:modal name="format-form" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-5" data-test="format-form">
            <div>
                <flux:heading size="lg">{{ $editing_id ? __('Edit format') : __('Add format') }}</flux:heading>
                <flux:subheading>{{ __('Patterns use placeholders. The email domain is fixed to :domain.', ['domain' => \App\Support\Registration\StudentEmailDomain::DOMAIN]) }}</flux:subheading>
            </div>

            <flux:input wire:model="name" :label="__('Name')" :placeholder="__('Batch F21 old format')" maxlength="100" required />

            <flux:input wire:model="email_template" :label="__('Email pattern')" :description="__('The part before :domain', ['domain' => \App\Support\Registration\StudentEmailDomain::DOMAIN])" placeholder="bsem{BATCH}-{DIGITS:3}" maxlength="100" required />

            <flux:input wire:model="registration_no_template" :label="__('Registration number pattern')" placeholder="BSEM{BATCH}-{DIGITS:3}" maxlength="100" required />

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <flux:select wire:model="program_code" :label="__('Program (optional)')">
                    <flux:select.option value="">{{ __('Any program') }}</flux:select.option>
                    @foreach ($this->programs as $program)
                        <flux:select.option :value="$program->code">{{ $program->code }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="batch" :label="__('Batch (optional)')" :description="__('Needed unless the pattern has {BATCH}.')" placeholder="F21" maxlength="3" />
            </div>

            <flux:checkbox wire:model="is_alumni" :label="__('Passed out (alumni)')" :description="__('These students are not asked for a current semester.')" />
            <flux:checkbox wire:model="is_active" :label="__('Active')" :description="__('Disabled formats are not accepted at registration.')" />

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save" data-test="save-format">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
