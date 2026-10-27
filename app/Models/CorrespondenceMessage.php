<?php

namespace App\Models;

use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\RemarkType;
use Database\Factories\CorrespondenceMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One message of an application's staff correspondence thread: a
 * forward, a reply, an HOD/Dean remark or a Registrar reply logged by
 * the Admin Officer. Staff-only: never loaded in any student view.
 *
 * @property int $application_id
 * @property CorrespondenceType $type
 * @property int $author_id
 * @property CorrespondenceParty $from_party
 * @property int|null $from_user_id
 * @property string|null $from_email
 * @property CorrespondenceParty $to_party
 * @property int|null $to_user_id
 * @property string|null $to_email
 * @property RemarkType|null $remark_type
 * @property string|null $body
 * @property CorrespondenceEmailStatus $email_status
 * @property int $email_attempts
 * @property string|null $email_error
 * @property Carbon|null $emailed_at
 * @property string|null $rfc_message_id
 * @property string|null $gmail_message_id
 * @property string|null $gmail_thread_id
 * @property string $idempotency_key
 * @property Carbon|null $seen_at
 * @property int|null $seen_by_id
 * @property Carbon $created_at
 */
class CorrespondenceMessage extends Model
{
    /** @use HasFactory<CorrespondenceMessageFactory> */
    use HasFactory, LogsActivity;

    const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'type',
        'author_id',
        'from_party',
        'from_user_id',
        'from_email',
        'to_party',
        'to_user_id',
        'to_email',
        'remark_type',
        'body',
        'email_status',
        'email_attempts',
        'email_error',
        'emailed_at',
        'rfc_message_id',
        'gmail_message_id',
        'gmail_thread_id',
        'idempotency_key',
        'seen_at',
        'seen_by_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => CorrespondenceType::class,
            'from_party' => CorrespondenceParty::class,
            'to_party' => CorrespondenceParty::class,
            'remark_type' => RemarkType::class,
            'email_status' => CorrespondenceEmailStatus::class,
            'emailed_at' => 'datetime',
            'seen_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['application_id', 'type', 'from_party', 'to_party', 'to_user_id', 'to_email', 'remark_type', 'body', 'email_status'])
            ->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Who wrote it in the portal: the sender, or for a logged Registrar
     * reply the Admin Officer who logged it.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seen_by_id');
    }

    /**
     * @return HasMany<CorrespondenceAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(CorrespondenceAttachment::class);
    }

    /**
     * The other side of this message, seen from the given party.
     */
    public function counterpartOf(CorrespondenceParty $party): CorrespondenceParty
    {
        return $this->from_party === $party ? $this->to_party : $this->from_party;
    }

    /**
     * The sender's name, or the Registrar's email for a logged reply.
     */
    public function senderName(): string
    {
        if ($this->from_user_id !== null) {
            return $this->sender->name;
        }

        return $this->from_email ?? $this->from_party->label();
    }

    public function recipientName(): string
    {
        if ($this->to_user_id !== null) {
            return $this->recipient->name;
        }

        return $this->to_email ?? $this->to_party->label();
    }
}
