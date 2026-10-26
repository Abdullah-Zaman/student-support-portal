<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Google\ConnectGmailAccount;
use App\Actions\Google\FindStaffForGoogleSignIn;
use App\Http\Controllers\Controller;
use App\Models\GoogleAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as GoogleUser;
use LogicException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Google OAuth for staff, through one callback URL: "connect" links a
 * staff member's Gmail (gmail.send, offline access) and "login" signs a
 * staff member in (openid, email and profile only).
 */
class GoogleAuthController extends Controller
{
    private const INTENT_SESSION_KEY = 'google_oauth_intent';

    public function connect(Request $request): SymfonyRedirectResponse
    {
        Gate::authorize('manage-gmail');

        $request->session()->put(self::INTENT_SESSION_KEY, 'connect');

        return $this->google()
            ->scopes([GoogleAccount::GMAIL_SEND_SCOPE])
            ->with([
                'access_type' => 'offline',
                'prompt' => 'consent',
                'include_granted_scopes' => 'true',
                'login_hint' => $request->user()->email,
            ])
            ->redirect();
    }

    public function login(Request $request): SymfonyRedirectResponse
    {
        $request->session()->put(self::INTENT_SESSION_KEY, 'login');

        return $this->google()
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function callback(Request $request): Response
    {
        $intent = $request->session()->pull(self::INTENT_SESSION_KEY);

        if ($intent === 'connect' && $request->user() !== null) {
            return $this->finishConnect($request);
        }

        if ($intent === 'login' && $request->user() === null) {
            return $this->finishLogin($request);
        }

        return redirect()->route($request->user() ? 'profile.edit' : 'login');
    }

    private function finishConnect(Request $request): RedirectResponse
    {
        Gate::authorize('manage-gmail');

        $googleUser = $this->googleUser($request);

        if (! $googleUser instanceof GoogleUser) {
            return redirect()->route('profile.edit')->with('gmail_error', __('Gmail was not connected. Google sign-in was cancelled or did not finish.'));
        }

        try {
            app(ConnectGmailAccount::class)->handle($request->user(), $googleUser);
        } catch (ValidationException $exception) {
            return redirect()->route('profile.edit')->with('gmail_error', $exception->validator->errors()->first());
        }

        return redirect()->route('profile.edit')->with('gmail_status', __('Gmail connected. Forwards will be emailed from this account.'));
    }

    private function finishLogin(Request $request): Response
    {
        $googleUser = $this->googleUser($request);

        if ($googleUser === null) {
            return redirect()->route('login')->withErrors(['email' => __('Google sign-in was cancelled or did not finish. Try again.')]);
        }

        try {
            $user = app(FindStaffForGoogleSignIn::class)->handle($googleUser);
        } catch (ValidationException $exception) {
            return redirect()->route('login')->withErrors($exception->errors());
        }

        Auth::login($user);
        $request->session()->regenerate();

        return app(LoginResponse::class)->toResponse($request);
    }

    /**
     * Null when the user denied access or the OAuth state did not match.
     */
    private function googleUser(Request $request): ?SocialiteUser
    {
        if ($request->filled('error')) {
            return null;
        }

        try {
            return Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The Google OAuth 2 driver, for scopes() and with() on the redirect.
     */
    private function google(): AbstractProvider
    {
        $provider = Socialite::driver('google');

        if (! $provider instanceof AbstractProvider) {
            throw new LogicException('The Google Socialite driver must be an OAuth 2 provider.');
        }

        return $provider;
    }
}
