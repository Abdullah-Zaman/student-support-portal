<?php

namespace App\Models;

use App\Enums\ForwardEmailStatus;
use App\Enums\ForwardTarget;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Legacy: one step of an application's old forwarding chain. Copied into
 * correspondence_messages and kept read-only (old audit-log entries point
 * here) until the table is dropped. Write correspondence instead.
 *
 * @property int $application_id
 * @property int $from_user_id
 * @property RoleName $from_role
 * @property ForwardTarget $to_role
 * @property int|null $to_user_id
 * @property string|null $to_email
 * @property string $note
 * @property ForwardEmailStatus $email_status
 * @property int $email_attempts
 * @property string|null $email_error
 * @property Carbon|null $emailed_at
 * @property string|null $gmail_message_id
 * @property Carbon $created_at
 */
class ApplicationForward extends Model
{
    use LogsActivity;

    const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'from_user_id',
        'from_role',
        'to_role',
        'to_user_id',
        'to_email',
        'note',
        'email_status',
        'email_attempts',
        'email_error',
        'emailed_at',
        'gmail_message_id',
    ];

    protected function casts(): array
    {
        return [
            'from_role' => RoleName::class,
            'to_role' => ForwardTarget::class,
            'email_status' => ForwardEmailStatus::class,
            'emailed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['application_id', 'from_role', 'to_role', 'to_user_id', 'to_email', 'note', 'email_status'])
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
     * @return HasMany<ApplicationRemark, $this>
     */
    public function remarks(): HasMany
    {
        return $this->hasMany(ApplicationRemark::class);
    }
}
