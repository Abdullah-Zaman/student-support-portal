<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\RegistrationValidationRules;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Program;
use App\Models\RegistrationFormat;
use App\Models\Student;
use App\Models\User;
use App\Support\Registration\RegistrationFormats;
use App\Support\Registration\StudentEmailDomain;
use App\Support\RegistrationNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, RegistrationValidationRules;

    /**
     * Validate and create a newly registered student account. The
     * student's department is the department of the chosen program.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['registration_no'] = strtoupper(trim((string) ($input['registration_no'] ?? '')));
        $input['email'] = StudentEmailDomain::normalize((string) ($input['email'] ?? ''));
        $program = $input['program'] ?? null;
        $format = $this->additionalFormat($input['registration_no'], $input['email'], $program);

        $validated = Validator::make($input, [
            'name' => $this->nameRules(),
            'program' => $this->programRules(),
            'registration_no' => $this->registrationNoRules($program),
            'email' => $this->emailRules($input['registration_no'], null, $program),
            'current_semester' => $this->currentSemesterRules($format['is_alumni'] ?? false),
            'password' => $this->passwordRules(),
        ], $this->registrationMessages())->validate();

        $isAlumni = $format['is_alumni'] ?? false;
        $batch = $format['batch'] ?? RegistrationNumber::parse($validated['registration_no'])['batch'] ?? '';

        $program = Program::withoutGlobalScopes()->where('code', $validated['program'])->firstOrFail();

        return DB::transaction(function () use ($validated, $batch, $program, $isAlumni) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => strtolower($validated['email']),
                'password' => $validated['password'],
                'department_id' => $program->department_id,
                'status' => UserStatus::PendingVerification,
            ]);

            $user->assignRole(RoleName::Student->value);

            Student::create([
                'user_id' => $user->id,
                'registration_no' => $validated['registration_no'],
                'program_id' => $program->id,
                'current_semester' => $isAlumni ? null : $validated['current_semester'],
                'batch' => $batch,
                'is_alumni' => $isAlumni,
            ]);

            return $user;
        });
    }

    /**
     * The additional format this registration uses, when it does not fit
     * the current program-based format with the matching email.
     *
     * @return array{rule: RegistrationFormat, program_code: string|null, batch: string, is_alumni: bool}|null
     */
    private function additionalFormat(string $registrationNo, string $email, ?string $program): ?array
    {
        $fitsCurrentFormat = RegistrationNumber::parse($registrationNo) !== null
            && $email === strtolower($registrationNo).StudentEmailDomain::DOMAIN;

        return $fitsCurrentFormat ? null : RegistrationFormats::matchPair($registrationNo, $email, $program);
    }
}
