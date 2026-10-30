<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Office>
 */
class OfficeFactory extends Factory
{
    protected $model = Office::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'name' => fake()->unique()->words(2, true),
            'code' => null,
            'is_active' => true,
            'available_to_hod' => false,
            'available_to_dean' => false,
        ];
    }
}
