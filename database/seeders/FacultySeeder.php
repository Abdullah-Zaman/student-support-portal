<?php

namespace Database\Seeders;

use App\Models\Faculty;
use Illuminate\Database\Seeder;

class FacultySeeder extends Seeder
{
    public function run(): void
    {
        Faculty::query()->firstOrCreate(
            ['code' => 'FOC'],
            ['name' => 'Faculty of Computing', 'is_active' => true],
        );
    }
}
