<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Faculty;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Code => [name, active on first seed]. IT and CS start inactive
     * until they have programs, an Admin Officer and categories.
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    private const COMPUTING_DEPARTMENTS = [
        'SE' => ['Software Engineering', true],
        'IT' => ['Information Technology', false],
        'CS' => ['Computer Science', false],
    ];

    /**
     * firstOrCreate, so re-seeding never overrides a department a
     * Super Admin has since activated, deactivated or renamed.
     */
    public function run(): void
    {
        $faculty = Faculty::query()->where('code', 'FOC')->firstOrFail();

        foreach (self::COMPUTING_DEPARTMENTS as $code => [$name, $isActive]) {
            Department::query()->firstOrCreate(
                ['code' => $code],
                ['faculty_id' => $faculty->id, 'name' => $name, 'is_active' => $isActive],
            );
        }
    }
}
