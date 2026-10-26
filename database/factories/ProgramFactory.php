<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * Five-letter codes, so they never collide with the seeded
     * four-letter ones (BSSE, BSDS, BSAI).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => Str::upper(fake()->unique()->lexify('BS???')),
            'name' => 'BS '.Str::title(fake()->unique()->word()).' Studies',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
