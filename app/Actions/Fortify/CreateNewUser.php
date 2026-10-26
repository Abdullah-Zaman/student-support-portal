<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\RegistrationValidationRules;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
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
        $input['registration_no'] = strtoupper((string) ($input['registration_no'] ?? ''));
        $program = $input['program'] ?? null;

        $validated = Validator::make($input, [
            'name' => $this->nameRules(),
            'program' => $this->programRules(),
            'registration_no' => $this->registrationNoRules($program),
            'email' => $this->emailRules($input['registration_no']),
            'current_semester' => $this->currentSemesterRules(),
            'password' => $this->passwordRules(),
        ], $this->registrationMessages())->validate();

        $batch = RegistrationNumber::parse($validated['registration_no'])['batch'] ?? '';

        $program = Program::withoutGlobalScopes()->where('code', $validated['program'])->firstOrFail();

        return DB::transaction(function () use ($validated, $batch, $program) {
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
                'current_semester' => $validated['current_semester'],
                'batch' => $batch,
            ]);

            return $user;
        });
    }
}
