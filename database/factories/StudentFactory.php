<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'registration_no' => sprintf(
                'SU92-BSSEM-F%d-%d',
                fake()->unique()->numberBetween(20, 25),
                fake()->unique()->numberBetween(100, 999),
            ),
            'program_id' => Program::factory(),
            'current_semester' => fake()->numberBetween(1, 8),
            'batch' => 'F'.fake()->numberBetween(20, 25),
        ];
    }
}
