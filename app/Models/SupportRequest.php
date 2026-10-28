<?php

namespace App\Models;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestStatus;
use Database\Factories\SupportRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A problem an Admin Officer, HOD or Dean reports to the Super Admin.
 * Visible only to its reporter and the Super Admin; never to students or
 * other staff. Separate from applications and staff conversations.
 *
 * @property int $reporter_id
 * @property SupportRequestCategory $category
 * @property string $subject
 * @property string $message
 * @property int|null $application_id
 * @property int|null $student_id
 * @property SupportRequestStatus $status
 * @property string|null $resolution_note
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 */
class SupportRequest extends Model
{
    /** @use HasFactory<SupportRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'reporter_id',
        'category',
        'subject',
        'message',
        'application_id',
        'student_id',
        'status',
        'resolution_note',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'category' => SupportRequestCategory::class,
            'status' => SupportRequestStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class)->withoutGlobalScopes();
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return HasMany<SupportRequestAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(SupportRequestAttachment::class);
    }

    /**
     * A reporter's own requests.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReportedBy(Builder $query, User $user): Builder
    {
        return $query->where('reporter_id', $user->id);
    }

    public function isResolved(): bool
    {
        return $this->status === SupportRequestStatus::Resolved;
    }
}
