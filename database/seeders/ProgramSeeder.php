<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Seeder;

class ProgramSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const SE_PROGRAMS = [
        'BSSE' => 'BS Software Engineering',
        'BSDS' => 'BS Data Science',
        'BSAI' => 'BS Artificial Intelligence',
    ];

    /**
     * firstOrCreate, so re-seeding never re-activates a program that a
     * Super Admin has since deactivated.
     */
    public function run(): void
    {
        $department = Department::query()->where('code', 'SE')->firstOrFail();

        foreach (self::SE_PROGRAMS as $code => $name) {
            Program::query()->firstOrCreate(
                ['code' => $code],
                ['department_id' => $department->id, 'name' => $name, 'is_active' => true],
            );
        }
    }
}
