<?php

namespace Database\Factories;

use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Models\Application;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * A forward from an Admin Officer to the HOD, already emailed. Override
 * the parties and users for anything else.
 *
 * @extends Factory<CorrespondenceMessage>
 */
class CorrespondenceMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $author = User::factory();

        return [
            'application_id' => Application::factory(),
            'type' => CorrespondenceType::Forward,
            'author_id' => $author,
            'from_party' => CorrespondenceParty::AdminOfficer,
            'from_user_id' => fn (array $attributes) => $attributes['author_id'],
            'to_party' => CorrespondenceParty::Hod,
            'to_user_id' => null,
            'conversation_id' => fn (array $attributes) => CorrespondenceConversation::between(
                $attributes['application_id'],
                $attributes['from_party'],
                $attributes['to_party'],
            )->id,
            'body' => fake()->sentence(),
            'email_status' => CorrespondenceEmailStatus::Sent,
            'rfc_message_id' => 'corr.'.Str::random(20).'@example.test',
            'idempotency_key' => (string) Str::uuid(),
        ];
    }
}
