<?php

namespace Database\Factories;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reporter_id' => User::factory(),
            'category' => SupportRequestCategory::SystemProblem,
            'subject' => fake()->sentence(5),
            'message' => fake()->paragraph(),
            'status' => SupportRequestStatus::Open,
        ];
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => SupportRequestStatus::Resolved,
            'resolution_note' => 'Fixed.',
            'resolved_at' => now(),
        ]);
    }
}
