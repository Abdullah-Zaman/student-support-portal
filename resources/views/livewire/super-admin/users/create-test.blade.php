<div class="flex flex-col gap-6 py-8">
    <x-page-header :title="__('Create test user')" :subtitle="__('For testing only. The account is created verified and active, with any email domain, and nothing is emailed.')" />

    <x-card class="max-w-2xl">
        <form wire:submit="save" class="flex flex-col gap-5" data-test="create-test-user-form">
            <flux:select wire:model.live="role" :label="__('Role')">
                @foreach ($this->roles as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:input wire:model="email" type="email" :label="__('Email')" :description="__('Any domain is allowed here.')" required />
            <flux:input wire:model="password" type="text" :label="__('Password')" :description="__('At least 8 characters. Shown as you type, since this is a test account.')" required />

            @if ($role === \App\Enums\RoleName::Student->value)
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <flux:input wire:model="registration_no" :label="__('Registration no.')" :description="__('The strict format is not enforced here.')" required />

                    <flux:select wire:model="program" :label="__('Program')">
                        @foreach ($this->programs as $program)
                            <flux:select.option :value="$program->code">{{ $program->code }} · {{ $program->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="current_semester" :label="__('Current semester')">
                        @foreach (range(1, 8) as $semester)
                            <flux:select.option :value="(string) $semester">{{ $semester }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="batch" :label="__('Batch')" :description="__('For example F22 or S23.')" required />
                </div>
            @elseif ($role === \App\Enums\RoleName::Dean->value)
                <flux:select wire:model="faculty_id" :label="__('Faculty')" :placeholder="__('Select a faculty')">
                    @foreach ($this->faculties as $faculty)
                        <flux:select.option :value="(string) $faculty->id">{{ $faculty->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <flux:select wire:model="department_id" :label="__('Department')" :placeholder="__('Select a department')">
                    @foreach ($this->departments as $department)
                        <flux:select.option :value="(string) $department->id">{{ $department->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary" icon="beaker" wire:loading.attr="disabled" wire:target="save" data-test="create-test-user-button">{{ __('Create test user') }}</flux:button>
            </div>
        </form>
    </x-card>

    @if ($this->recentTestUsers->isNotEmpty())
        <x-panel :title="__('Recent test users')">
            <ul class="m-0 flex list-none flex-col p-0">
                @foreach ($this->recentTestUsers as $testUser)
                    <li class="flex flex-wrap items-center gap-2 border-t border-border-soft px-[22px] py-3 first:border-t-0" wire:key="test-user-{{ $testUser->id }}">
                        <span class="font-semibold text-ink">{{ $testUser->name }}</span>
                        <x-test-badge />
                        <span class="text-[13px] text-subtle">{{ $testUser->email }} · {{ $testUser->roles->pluck('name')->map(fn ($role) => \Illuminate\Support\Str::headline($role))->join(', ') }}</span>
                    </li>
                @endforeach
            </ul>
        </x-panel>
    @endif
</div>
