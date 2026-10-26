<?php

namespace App\Concerns;

use App\Models\Student;
use App\Models\User;
use App\Support\RegistrationNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait RegistrationValidationRules
{
    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function nameRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function programRules(): array
    {
        return ['required', 'string', Rule::in(RegistrationNumber::openProgramCodes())];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function currentSemesterRules(): array
    {
        return ['required', 'integer', 'between:1,8'];
    }

    /**
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function registrationNoRules(?string $program, ?int $ignoreStudentId = null): array
    {
        $unique = Rule::unique(Student::class, 'registration_no');

        if ($ignoreStudentId) {
            $unique = $unique->ignore($ignoreStudentId);
        }

        return [
            'required',
            'string',
            function (string $attribute, mixed $value, Closure $fail) use ($program) {
                $parsed = RegistrationNumber::parse((string) $value);

                if ($parsed === null) {
                    $fail(RegistrationNumber::parse((string) $value, openProgramsOnly: false) === null
                        ? __('Invalid registration number format.')
                        : __('Registration is not open for this program.'));

                    return;
                }

                if ($program && $parsed['program_code'] !== $program) {
                    $fail(__("This registration number doesn't match the selected program."));
                }
            },
            $unique,
        ];
    }

    /**
     * @return array<int, ValidationRule|Closure|array<mixed>|string>
     */
    protected function emailRules(?string $registrationNo, ?int $ignoreUserId = null): array
    {
        $unique = Rule::unique(User::class);

        if ($ignoreUserId) {
            $unique = $unique->ignore($ignoreUserId);
        }

        $domain = config('students.email_domain');

        $rules = [
            'required',
            'string',
            'email',
            'max:255',
            function (string $attribute, mixed $value, Closure $fail) use ($domain) {
                if (! str_ends_with(strtolower((string) $value), strtolower($domain))) {
                    $fail(__('Use your university email ending in :domain.', ['domain' => $domain]));
                }
            },
            $unique,
        ];

        if (config('students.enforce_email_matches_registration_no') && $registrationNo) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail) use ($registrationNo) {
                $expected = strtolower($registrationNo).config('students.email_domain');

                if (strtolower((string) $value) !== $expected) {
                    $fail(__('Your university email must match your registration number.'));
                }
            };
        }

        return $rules;
    }

    /**
     * Friendly overrides for the rules above that would otherwise fall
     * back to Laravel's generic wording (unique, in, between, ...).
     *
     * @return array<string, string>
     */
    protected function registrationMessages(): array
    {
        return [
            'name.required' => __('Enter your full name.'),
            'program.required' => __('Select your program.'),
            'program.in' => __('Select a valid program.'),
            'current_semester.required' => __('Select your current semester.'),
            'current_semester.between' => __('Select a semester between 1 and 8.'),
            'registration_no.required' => __('Enter your registration number.'),
            'registration_no.unique' => __('This registration number is already registered.'),
            'email.required' => __('Enter your university email.'),
            'email.email' => __('Enter a valid email address.'),
            'email.unique' => __('This email is already registered.'),
            'password.confirmed' => __('The passwords do not match.'),
        ];
    }
}
