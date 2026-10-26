<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * The SE department's HOD and the Faculty of Computing's Dean. Only
 * creates them when missing: an existing account (and its password) is
 * never overwritten.
 */
class ReviewerUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardAgainstDefaultPasswordsInProduction();

        $department = Department::query()->where('code', 'SE')->firstOrFail();
        $faculty = Faculty::query()->where('code', 'FOC')->firstOrFail();

        $hod = User::query()->firstOrCreate(
            ['email' => config('seeding.hod.email')],
            [
                'name' => 'SE Head of Department',
                'password' => config('seeding.hod.password'),
                'department_id' => $department->id,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
        $hod->syncRoles([RoleName::Hod->value]);

        $dean = User::query()->firstOrCreate(
            ['email' => config('seeding.dean.email')],
            [
                'name' => 'Dean, Faculty of Computing',
                'password' => config('seeding.dean.password'),
                'faculty_id' => $faculty->id,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ],
        );
        $dean->syncRoles([RoleName::Dean->value]);
    }

    private function guardAgainstDefaultPasswordsInProduction(): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $default = config('seeding.default_password');

        if (config('seeding.hod.password') === $default || config('seeding.dean.password') === $default) {
            throw new RuntimeException(
                'Refusing to seed HOD/Dean users with the default password in production. '
                .'Set SEED_HOD_PASSWORD and SEED_DEAN_PASSWORD in .env.',
            );
        }
    }
}
