<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Enums\UserStatus;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Http\Responses\LoginResponse;
use App\Http\Responses\PasswordResetResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponseContract::class, GenericPasswordResetLinkResponse::class);
        $this->app->singleton(FailedPasswordResetLinkRequestResponseContract::class, GenericPasswordResetLinkResponse::class);
        $this->app->singleton(PasswordResetResponseContract::class, PasswordResetResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();

        // Fortify's own service provider registers its routes during its
        // boot(), which — since it's package-discovered — runs after this
        // one. Routes don't exist yet here, so wait until every provider
        // has booted.
        $this->app->booted($this->throttleRegistration(...));
        $this->app->booted($this->throttlePasswordResetRequests(...));
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('livewire.auth.login'));
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));
        Fortify::registerView(fn () => view('livewire.auth.register'));
        Fortify::resetPasswordView(fn () => view('livewire.auth.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('livewire.auth.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        // All students share the university's one public IP, so this stays
        // generous rather than per-login's tight per-credential limit.
        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('password-reset', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::email())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
    }

    /**
     * Only block suspended accounts at login. Unverified accounts are
     * allowed to authenticate — the "verified" middleware is what confines
     * them to the verification-notice page until they verify.
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::where(Fortify::username(), $request->input(Fortify::username()))->first();

            if (! $user || ! Hash::check($request->input('password'), $user->password)) {
                return null;
            }

            if ($user->status === UserStatus::Suspended) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Your account has been suspended. Contact the SE Department Admin Office.',
                ]);
            }

            return $user;
        });
    }

    /**
     * Fortify's registration route ships with no throttle at all.
     */
    /**
     * Route::getRoutes()->getByName() reads a name-lookup index that's
     * built when a route is added to the collection — before Fortify's
     * chained ->name() call sets its name — so it's stale here. Searching
     * the live route list directly avoids that.
     */
    private function throttleRegistration(): void
    {
        foreach (Route::getRoutes()->get() as $route) {
            if ($route->getName() === 'register.store') {
                $route->middleware('throttle:registration');

                return;
            }
        }
    }

    /**
     * Fortify's password.email route (the "forgot password" request) ships
     * with no throttle at all, same gap as registration above.
     */
    private function throttlePasswordResetRequests(): void
    {
        foreach (Route::getRoutes()->get() as $route) {
            if ($route->getName() === 'password.email') {
                $route->middleware('throttle:password-reset');

                return;
            }
        }
    }
}
