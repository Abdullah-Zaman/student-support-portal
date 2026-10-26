<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FacultySeeder::class,
            DepartmentSeeder::class,
            ProgramSeeder::class,
            RoleSeeder::class,
            ApplicationCategorySeeder::class,
            SettingSeeder::class,
            AdminUserSeeder::class,
            ReviewerUserSeeder::class,
        ]);
    }
}
