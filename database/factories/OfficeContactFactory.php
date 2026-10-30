<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\OfficeContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfficeContact>
 */
class OfficeContactFactory extends Factory
{
    protected $model = OfficeContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'name' => fake()->name(),
            'designation' => fake()->jobTitle(),
            'email' => fake()->unique()->userName().'@superior.edu.pk',
            'is_active' => true,
        ];
    }
}
