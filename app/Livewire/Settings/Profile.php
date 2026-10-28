<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Enums\RoleName;
use Flux\Flux;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $email_signature = '';

    /**
     * Mount the component. Students manage their academic profile at
     * /student/profile instead — this page would let them change their
     * name/email, bypassing the university email and verification rules.
     */
    public function mount(): void
    {
        if (Auth::user()->hasRole(RoleName::Student->value)) {
            $this->redirect(route('student.profile'), navigate: true);

            return;
        }

        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->email_signature = Auth::user()->emailSignature();
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        abort_if($user->hasRole(RoleName::Student->value), 403);

        $validated = $this->validate([
            ...$this->profileRules($user->id),
            'email_signature' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['email_signature'] = trim((string) $validated['email_signature']) !== '' ? $validated['email_signature'] : null;

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        Flux::toast(variant: 'success', text: __('Profile updated.'));
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Flux::toast(text: __('A new verification link has been sent to your email address.'));
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        $user = Auth::user();

        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }
}
