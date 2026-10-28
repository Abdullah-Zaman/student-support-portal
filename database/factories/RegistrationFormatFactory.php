<?php

namespace Database\Factories;

use App\Models\RegistrationFormat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Defaults to a simple old format: email "bsemf21-040", registration
 * number "BSEMF21-040", batch from the template.
 *
 * @extends Factory<RegistrationFormat>
 */
class RegistrationFormatFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Old format '.fake()->unique()->numerify('###'),
            'email_template' => 'bsem{BATCH}-{DIGITS:3}',
            'registration_no_template' => 'BSEM{BATCH}-{DIGITS:3}',
            'program_code' => null,
            'batch' => null,
            'is_alumni' => false,
            'is_active' => true,
        ];
    }

    public function alumni(): static
    {
        return $this->state(fn () => ['is_alumni' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
