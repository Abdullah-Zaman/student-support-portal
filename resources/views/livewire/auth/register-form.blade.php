<div class="flex flex-col gap-6">
    <x-auth-header :title="__('Create an account')" :description="__('Only students of the Software Engineering Department (BS SE, BS DS, BS AI) with a university email can register.')" />

    <form wire:submit="register" class="flex flex-col gap-6">
        <!-- Name -->
        <x-validated-field>
            <flux:input
                wire:model.live="name"
                wire:blur="blurred('name')"
                :label="__('Full name')"
                type="text"
                required
                autofocus
                autocomplete="name"
                :placeholder="__('Full name')"
            />
        </x-validated-field>

        <!-- Program -->
        <x-validated-field>
            <flux:select wire:model.live="program" wire:blur="blurred('program')" :label="__('Program')" :placeholder="__('Select your program')" required>
                @foreach ($this->programs as $programOption)
                    <flux:select.option :value="$programOption->code" wire:key="program-{{ $programOption->id }}">{{ $programOption->name }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-validated-field>

        <!-- Registration No. -->
        @php
            $registrationNoExample = $this->registrationNoExample;
        @endphp
        <x-validated-field>
            <div class="flex flex-col gap-1">
                <flux:input
                    wire:model.live="registration_no"
                    wire:blur="blurred('registration_no')"
                    :label="__('Registration no.')"
                    type="text"
                    required
                    autocomplete="off"
                    :placeholder="$registrationNoExample"
                />
                <span class="text-xs text-subtle">{{ __('Format: :example', ['example' => $registrationNoExample]) }}</span>
            </div>
        </x-validated-field>

        <!-- Email Address -->
        <x-validated-field>
            <flux:input
                wire:model.live="email"
                wire:blur="blurred('email')"
                :label="__('University email')"
                type="email"
                required
                autocomplete="email"
                placeholder="name@superior.edu.pk"
            />
        </x-validated-field>

        <!-- Current Semester -->
        <x-validated-field>
            <flux:select wire:model.live="current_semester" wire:blur="blurred('current_semester')" :label="__('Current semester')" :placeholder="__('Select semester')" required>
                @foreach (range(1, 8) as $semester)
                    <flux:select.option :value="(string) $semester">{{ $semester }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-validated-field>

        <!-- Password -->
        <x-validated-field>
            <flux:input
                wire:model.live="password"
                wire:blur="blurred('password')"
                :label="__('Password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
        </x-validated-field>

        <!-- Confirm Password -->
        <x-validated-field>
            <flux:input
                wire:model.live="password_confirmation"
                wire:blur="blurred('password_confirmation')"
                :label="__('Confirm password')"
                type="password"
                required
                autocomplete="new-password"
                :placeholder="__('Confirm password')"
                passwordrules="{{ \Illuminate\Validation\Rules\Password::defaults()->toPasswordRulesString() }}"
                viewable
            />
        </x-validated-field>

        <div class="flex items-center justify-end">
            <flux:button type="submit" variant="primary" class="w-full" data-test="register-user-button">
                {{ __('Create account') }}
            </flux:button>
        </div>
    </form>

    <div class="space-x-1 rtl:space-x-reverse text-center text-sm text-muted">
        <span>{{ __('Already have an account?') }}</span>
        <flux:link :href="route('login')" wire:navigate>{{ __('Log in') }}</flux:link>
    </div>
</div>
