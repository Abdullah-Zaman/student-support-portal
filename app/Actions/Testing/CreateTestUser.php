<?php

namespace App\Actions\Testing;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use App\Support\TestUserCreation;
use Illuminate\Support\Facades\DB;

/**
 * Creates a verified, active test account (is_test = true) for the
 * Super Admin's test-user tool. It sends nothing: no verification mail,
 * no welcome mail, no notification. The caller authorizes and validates;
 * this refuses to run unless the tool is enabled.
 */
class CreateTestUser
{
    /**
     * @param  array{name: string, email: string, password: string, role: string, department_id?: string|int|null, faculty_id?: string|int|null, registration_no?: string|null, program?: string|null, current_semester?: string|int|null, batch?: string|null}  $data
     */
    public function handle(User $actor, array $data): User
    {
        abort_unless(TestUserCreation::enabled(), 403);

        return DB::transaction(function () use ($actor, $data): User {
            $isStudent = $data['role'] === RoleName::Student->value;
            $isDean = $data['role'] === RoleName::Dean->value;
            $program = $isStudent
                ? Program::withoutGlobalScopes()->where('code', $data['program'] ?? '')->firstOrFail()
                : null;
            $departmentId = $isDean ? null : ($program->department_id ?? ($data['department_id'] ?? null));

            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'password' => $data['password'],
                'department_id' => $departmentId ?: null,
                'faculty_id' => $isDean ? (($data['faculty_id'] ?? null) ?: null) : null,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]);

            $user->forceFill(['is_test' => true])->save();
            $user->assignRole($data['role']);

            if ($program !== null) {
                Student::create([
                    'user_id' => $user->id,
                    'registration_no' => strtoupper((string) ($data['registration_no'] ?? '')),
                    'program_id' => $program->id,
                    'current_semester' => (int) ($data['current_semester'] ?? 1),
                    'batch' => (string) ($data['batch'] ?? ''),
                ]);
            }

            activity()
                ->causedBy($actor)
                ->performedOn($user)
                ->withProperties(['role' => $data['role'], 'is_test' => true])
                ->log('test user created');

            return $user;
        });
    }
}
