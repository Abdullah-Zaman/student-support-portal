<?php

namespace App\Providers;

use App\Listeners\ActivateVerifiedUser;
use App\Listeners\LogOutOtherSessionsOnPasswordReset;
use App\Listeners\RecordFailedLogin;
use App\Listeners\RecordSuccessfulLogin;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Support\BrandedMailMessage;
use App\Support\Forwarding\ForwardMailer;
use App\Support\Forwarding\GmailForwardMailer;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(ForwardMailer::class, GmailForwardMailer::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMailSafety();

        Gate::policy(Activity::class, ActivityPolicy::class);

        Gate::define('manage-gmail', fn (User $user): bool => $user->staffRole() !== null);

        Event::listen(Verified::class, ActivateVerifiedUser::class);
        Event::listen(Login::class, RecordSuccessfulLogin::class);
        Event::listen(Failed::class, RecordFailedLogin::class);
        Event::listen(PasswordReset::class, LogOutOtherSessionsOnPasswordReset::class);

        VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $message = (new MailMessage)
                ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
                ->line(__('Welcome to the Software Engineering Department Student Support Portal. Please verify your university email address to activate your account.'))
                ->action(__('Verify Email Address'), $url)
                ->line(__('If you did not create an account, no further action is required.'));

            return BrandedMailMessage::finalize($message, __('Verify your email'));
        });

        ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $message = (new MailMessage)
                ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
                ->line(__('You are receiving this email because we received a password reset request for your account.'))
                ->action(__('Reset Password'), $url)
                ->line(__('This password reset link will expire in :count minutes.', ['count' => config('auth.passwords.users.expire')]))
                ->line(__('If you did not request a password reset, no further action is required.'));

            return BrandedMailMessage::finalize($message, __('Reset your password'));
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Outside production, MAIL_ALWAYS_TO (if set) redirects every outgoing
     * email to one inbox — so a non-production environment pointed at a
     * real SMTP provider never emails an actual student or admin.
     */
    protected function configureMailSafety(): void
    {
        $alwaysTo = config('mail.always_to');

        if (! app()->isProduction() && is_string($alwaysTo) && $alwaysTo !== '') {
            Mail::alwaysTo($alwaysTo);
        }
    }
}
