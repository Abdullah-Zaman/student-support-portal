<?php

namespace App\Models;

use App\Enums\RemarkType;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An HOD's or Dean's remark on an application forwarded to them.
 * Staff-only: never loaded in any student view.
 *
 * @property int $application_id
 * @property int $application_forward_id
 * @property int $author_id
 * @property RoleName $author_role
 * @property RemarkType $type
 * @property string|null $body
 * @property Carbon $created_at
 */
class ApplicationRemark extends Model
{
    use LogsActivity;

    const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'application_forward_id',
        'author_id',
        'author_role',
        'type',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'author_role' => RoleName::class,
            'type' => RemarkType::class,
            'created_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['application_id', 'author_role', 'type', 'body'])
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
     * @return BelongsTo<ApplicationForward, $this>
     */
    public function forward(): BelongsTo
    {
        return $this->belongsTo(ApplicationForward::class, 'application_forward_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
