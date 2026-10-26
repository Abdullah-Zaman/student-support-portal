<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Welcome back')" :description="__('Log in with your university email to continue.')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-[18px]">
            @csrf

            <div
                x-data="{
                    touched: false,
                    valid: true,
                    wrongDomain: false,
                    check(value) {
                        this.valid = value === '' || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
                        this.wrongDomain = value !== '' && !value.trim().toLowerCase().endsWith('@superior.edu.pk');
                    },
                }"
            >
                <flux:input
                    name="email"
                    :label="__('University email')"
                    :value="old('email')"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="name@superior.edu.pk"
                    x-on:blur="touched = true; check($event.target.value)"
                    x-on:input="if (touched) check($event.target.value)"
                />
                <p x-show="touched && !valid" class="mt-1 text-sm text-red-600">{{ __('Enter a valid email address.') }}</p>
                <p x-show="touched && valid && wrongDomain" class="mt-1 text-sm text-amber-600">{{ __('Use your university email (@superior.edu.pk).') }}</p>
            </div>

            <flux:input
                name="password"
                :label="__('Password')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Enter your password')"
                viewable
            />

            <flux:checkbox name="remember" :label="__('Keep me signed in on this device')" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('Log in') }}
            </flux:button>

            @if (Route::has('password.request'))
                <flux:link class="self-center text-sm font-semibold" :href="route('password.request')" wire:navigate>
                    {{ __('Forgot your password?') }}
                </flux:link>
            @endif
        </form>

        @if (filled(config('services.google.client_id')))
            <div class="flex items-center gap-3 text-[13px] text-placeholder">
                <span class="h-px flex-1 bg-border-soft"></span>
                {{ __('Staff') }}
                <span class="h-px flex-1 bg-border-soft"></span>
            </div>

            <flux:button :href="route('google.login')" variant="outline" class="w-full" data-test="google-login-button">
                <svg class="size-4.5" viewBox="0 0 48 48" aria-hidden="true">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                    <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
                {{ __('Sign in with Google') }}
            </flux:button>
        @endif

        <div class="flex items-center gap-3 text-[13px] text-placeholder">
            <span class="h-px flex-1 bg-border-soft"></span>
            {{ __('New to the portal?') }}
            <span class="h-px flex-1 bg-border-soft"></span>
        </div>

        <flux:button :href="route('register')" variant="outline" class="w-full" wire:navigate>
            {{ __('Create a student account') }}
        </flux:button>

        <p class="m-0 text-center text-[13px] leading-relaxed text-muted">
            {{ __('Only Software Engineering students with an @superior.edu.pk email can register. Trouble logging in? Contact the SE Admin Office.') }}
        </p>
    </div>
</x-layouts::auth>
