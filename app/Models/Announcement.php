<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAdminDepartment;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $department_id
 * @property bool $is_active
 * @property bool $requires_acknowledgement
 * @property string|null $survey_url
 * @property Carbon|null $publish_at
 * @property Carbon|null $expires_at
 */
class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, LogsActivity, ScopedToAdminDepartment;

    protected $fillable = [
        'department_id',
        'created_by',
        'title',
        'body',
        'image_path',
        'attachment_path',
        'attachment_original_name',
        'publish_at',
        'expires_at',
        'is_active',
        'requires_acknowledgement',
        'survey_url',
    ];

    protected function casts(): array
    {
        return [
            'publish_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
            'requires_acknowledgement' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'body', 'image_path', 'attachment_path', 'publish_at', 'expires_at', 'is_active', 'requires_acknowledgement', 'survey_url'])
            ->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementAcknowledgement, $this>
     */
    public function acknowledgements(): HasMany
    {
        return $this->hasMany(AnnouncementAcknowledgement::class);
    }

    /**
     * The oldest mandatory announcement the student must still
     * acknowledge: current, in their own department, not yet acknowledged.
     */
    public static function nextPendingFor(User $student): ?self
    {
        return static::withoutGlobalScopes()
            ->visibleToStudents()
            ->where('department_id', $student->department_id)
            ->where('requires_acknowledgement', true)
            ->whereDoesntHave('acknowledgements', fn (Builder $query) => $query->where('user_id', $student->id))
            ->oldest('id')
            ->first();
    }

    /**
     * Active, published (or publish_at in the past/null) and not yet
     * expired — the single rule for what a student may ever see, shared
     * by the policy and by any query that lists announcements for them.
     */
    public function isCurrentlyVisible(): bool
    {
        return $this->is_active
            && ($this->publish_at === null || $this->publish_at->isPast())
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    public function scopeVisibleToStudents(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $query) {
                $query->whereNull('publish_at')->orWhere('publish_at', '<=', now());
            })
            ->where(function (Builder $query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    /**
     * Scheduled (publish_at in the future), Expired, Inactive, or Live —
     * for the admin list's status column.
     */
    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Inactive';
        }

        if ($this->publish_at !== null && $this->publish_at->isFuture()) {
            return 'Scheduled';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'Expired';
        }

        return 'Live';
    }

    /**
     * Published within the last 3 days — drives the "New" badge.
     */
    public function isRecentlyPublished(): bool
    {
        $publishedAt = $this->publish_at ?? $this->created_at;

        return $publishedAt !== null && $publishedAt->greaterThan(now()->subDays(3));
    }
}
