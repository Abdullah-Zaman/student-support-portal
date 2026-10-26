<?php

namespace Database\Factories;

use App\Models\GoogleAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleAccount>
 */
class GoogleAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'google_email' => fn (array $attributes) => User::query()->whereKey($attributes['user_id'])->value('email') ?? fake()->safeEmail(),
            'access_token' => 'ya29.'.fake()->sha256(),
            'refresh_token' => '1//'.fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'scopes' => 'openid email profile '.GoogleAccount::GMAIL_SEND_SCOPE,
            'needs_reconnect' => false,
            'connected_at' => now(),
        ];
    }

    /**
     * The access token has expired, so the next send refreshes it.
     */
    public function expired(): static
    {
        return $this->state(fn () => ['token_expires_at' => now()->subMinute()]);
    }

    /**
     * Google refused the refresh token (revoked or expired).
     */
    public function needsReconnect(): static
    {
        return $this->state(fn () => ['needs_reconnect' => true]);
    }
}
