<?php

namespace Database\Factories;

use App\Enums\TimetableVersionStatus;
use App\Models\Department;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimetableVersion>
 */
class TimetableVersionFactory extends Factory
{
    protected $model = TimetableVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'version_no' => fake()->unique()->numberBetween(1, 100000),
            'uploaded_by' => User::factory(),
            'original_name' => 'Timetable.xlsx',
            'file_path' => 'timetables/'.fake()->uuid().'.xlsx',
            'status' => TimetableVersionStatus::Draft,
            'class_count' => 0,
            'unparsed_cells' => [],
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TimetableVersionStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TimetableVersionStatus::Archived,
            'published_at' => now()->subWeek(),
        ]);
    }
}
