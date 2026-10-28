<?php

namespace App\Livewire\Auth;

use App\Concerns\PasswordValidationRules;
use App\Concerns\RegistrationValidationRules;
use App\Models\Program;
use App\Support\Registration\RegistrationFormats;
use App\Support\Registration\StudentEmailDomain;
use App\Support\RegistrationNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * @property-read bool $isAlumniFormat
 */
#[Title('Register')]
class Register extends Component
{
    use PasswordValidationRules, RegistrationValidationRules;

    public string $name = '';

    public string $registration_no = '';

    public string $email = '';

    public string $program = '';

    public string $current_semester = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * Registration number is always uppercased live, as the student types.
     */
    public function updatedRegistrationNo(string $value): void
    {
        $this->registration_no = strtoupper($value);
    }

    /**
     * Fires on every keystroke (all fields use wire:model.live). Never
     * shows a *new* error here — only refreshes one that already exists,
     * so it clears the moment the value becomes valid. New errors only
     * appear on blur (see blurred()). Related-field checks are the one
     * exception: they may introduce a new error on the related field.
     */
    public function updated(string $property): void
    {
        // The "confirmed" rule — and its error — lives on the password
        // field, not password_confirmation.
        if ($property === 'password_confirmation') {
            if ($this->getErrorBag()->has('password')) {
                $this->revalidate('password');
            }

            return;
        }

        if ($this->getErrorBag()->has($property)) {
            $this->revalidate($property);
        }

        $this->revalidateRelated($property);
    }

    /**
     * Validate a field on blur — the only place a *new* error appears
     * for the field the student is actually editing.
     */
    public function blurred(string $property): void
    {
        // The "confirmed" rule — and its error — lives on the password
        // field, not password_confirmation.
        if ($property === 'password_confirmation') {
            $this->revalidate('password');

            return;
        }

        $this->revalidate($property);
    }

    private function revalidate(string $property): void
    {
        $rules = $this->rulesFor($property);

        if ($rules !== null) {
            $this->validateOnly($property, [$property => $rules], $this->registrationMessages());
        }
    }

    /**
     * Re-check the field related to whichever one just changed — but only
     * if that related field already has a value worth checking.
     */
    private function revalidateRelated(string $property): void
    {
        $related = match ($property) {
            'program' => 'registration_no',
            'registration_no' => 'email',
            'password' => 'password_confirmation',
            default => null,
        };

        if ($related === null || $this->{$related} === '') {
            return;
        }

        if ($related === 'password_confirmation') {
            $this->revalidate('password');

            return;
        }

        $this->revalidate($related);
    }

    /**
     * @return array<int, mixed>|null
     */
    private function rulesFor(string $property): ?array
    {
        return match ($property) {
            'name' => $this->nameRules(),
            'program' => $this->programRules(),
            'registration_no' => $this->registrationNoRules($this->program ?: null),
            'email' => $this->emailRules($this->registration_no ?: null, null, $this->program ?: null),
            'current_semester' => $this->currentSemesterRules($this->isAlumniFormat),
            'password' => $this->passwordRules(),
            default => null,
        };
    }

    /**
     * @return Collection<int, Program>
     */
    #[Computed]
    public function programs(): Collection
    {
        return Program::withoutGlobalScopes()->openForRegistration()->orderBy('name')->get();
    }

    /**
     * Whether the registration number and email fit an additional
     * "passed out" format, in which case there is no current semester.
     */
    #[Computed]
    public function isAlumniFormat(): bool
    {
        if ($this->registration_no === '' || $this->email === '') {
            return false;
        }

        $fitsCurrentFormat = RegistrationNumber::parse($this->registration_no) !== null
            && StudentEmailDomain::normalize($this->email) === strtolower($this->registration_no).StudentEmailDomain::DOMAIN;

        return ! $fitsCurrentFormat
            && (RegistrationFormats::matchPair($this->registration_no, $this->email, $this->program ?: null)['is_alumni'] ?? false);
    }

    #[Computed]
    public function registrationNoExample(): string
    {
        return RegistrationNumber::example($this->program ?: ($this->programs()->first()->code ?? 'BSSE'));
    }

    public function register(CreatesNewUsers $creator, StatefulGuard $guard): void
    {
        $user = $creator->create([
            'name' => $this->name,
            'registration_no' => $this->registration_no,
            'email' => $this->email,
            'program' => $this->program,
            'current_semester' => $this->isAlumniFormat ? '' : $this->current_semester,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        event(new Registered($user));

        // SessionGuard::login() already regenerates the session ID.
        $guard->login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.auth.register-form');
    }
}
