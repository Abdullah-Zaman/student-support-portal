<?php

namespace Database\Factories;

use App\Models\Faculty;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Faculty>
 */
class FacultyFactory extends Factory
{
    protected $model = Faculty::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Faculty of '.Str::title(fake()->unique()->word()),
            'code' => Str::upper(fake()->unique()->lexify('F???')),
            'is_active' => true,
        ];
    }
}
