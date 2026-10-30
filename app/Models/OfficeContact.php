<?php

namespace App\Models;

use Database\Factories\OfficeContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One email recipient inside an office. Never deleted, only deactivated.
 *
 * @property int $office_id
 * @property string $name
 * @property string|null $designation
 * @property string $email
 * @property bool $is_active
 */
class OfficeContact extends Model
{
    /** @use HasFactory<OfficeContactFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'office_id',
        'name',
        'designation',
        'email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['office_id', 'name', 'designation', 'email', 'is_active'])
            ->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Office, $this>
     */
    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class)->withoutGlobalScopes();
    }
}
