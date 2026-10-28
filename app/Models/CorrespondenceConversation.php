<?php

namespace App\Models;

use App\Enums\CorrespondenceParty;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * A private staff conversation between two parties on one application:
 * Admin Officer ↔ HOD, Admin Officer ↔ Dean, HOD ↔ Dean, HOD ↔ Registrar
 * or Dean ↔ Registrar. Only the two parties' portal users can read it;
 * the Registrar is email-only. Every read goes through visibleTo().
 *
 * @property int $application_id
 * @property CorrespondenceParty $party_a
 * @property CorrespondenceParty $party_b
 */
class CorrespondenceConversation extends Model
{
    protected $fillable = ['application_id', 'party_a', 'party_b'];

    protected function casts(): array
    {
        return [
            'party_a' => CorrespondenceParty::class,
            'party_b' => CorrespondenceParty::class,
        ];
    }

    /**
     * Find or start the conversation between two parties on an application.
     */
    public static function between(int $applicationId, CorrespondenceParty $first, CorrespondenceParty $second): self
    {
        [$a, $b] = $first->order() <= $second->order() ? [$first, $second] : [$second, $first];
        $attributes = ['application_id' => $applicationId, 'party_a' => $a, 'party_b' => $b];

        try {
            return self::query()->firstOrCreate($attributes);
        } catch (UniqueConstraintViolationException) {
            return self::query()->where($attributes)->firstOrFail();
        }
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class)->withoutGlobalScopes();
    }

    /**
     * @return HasMany<CorrespondenceMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(CorrespondenceMessage::class, 'conversation_id');
    }

    /**
     * @return HasOne<CorrespondenceMessage, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(CorrespondenceMessage::class, 'conversation_id')->latestOfMany('id');
    }

    /**
     * Conversations the staff member is a participant of: their own
     * party is one side, and the application is in their department (or,
     * for a Dean, faculty). The Super Admin has no party, so sees none.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $party = CorrespondenceParty::forRole($user->staffRole());

        if ($party === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->where(fn (Builder $query) => $query->where('party_a', $party)->orWhere('party_b', $party))
            ->whereHas('application', fn (Builder $application) => $party === CorrespondenceParty::Dean
                ? $application->whereHas('department', fn (Builder $department) => $department->where('faculty_id', $user->faculty_id))
                : $application->where('applications.department_id', $user->department_id));
    }

    public function isParticipant(User $user): bool
    {
        return self::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    public function involves(CorrespondenceParty $party): bool
    {
        return $this->party_a === $party || $this->party_b === $party;
    }

    /**
     * The side that is not the given party.
     */
    public function counterpartOf(CorrespondenceParty $party): CorrespondenceParty
    {
        return $this->party_a === $party ? $this->party_b : $this->party_a;
    }

    public function isWithRegistrar(): bool
    {
        return $this->involves(CorrespondenceParty::Registrar);
    }

    /**
     * "Zeeshan Ahmed ↔ HOD": the viewer's counterpart, by name when it
     * is a portal user.
     */
    public function titleFor(User $viewer): string
    {
        $counterpart = $this->counterpartOf(CorrespondenceParty::forRole($viewer->staffRole()) ?? $this->party_a);
        $name = $this->counterpartUser($counterpart)?->name;

        return $name !== null ? $name.' ('.__($counterpart->label()).')' : __($counterpart->label());
    }

    private function counterpartUser(CorrespondenceParty $counterpart): ?User
    {
        $message = $this->messages()
            ->where(fn (Builder $query) => $query->where('from_party', $counterpart)->whereNotNull('from_user_id')
                ->orWhere(fn (Builder $query) => $query->where('to_party', $counterpart)->whereNotNull('to_user_id')))
            ->latest('id')
            ->first();

        if ($message === null) {
            return null;
        }

        return $message->from_party === $counterpart ? $message->sender : $message->recipient;
    }
}
