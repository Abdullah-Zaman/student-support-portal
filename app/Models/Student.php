<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAdminDepartmentViaUser;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, LogsActivity, ScopedToAdminDepartmentViaUser;

    protected $fillable = [
        'user_id',
        'registration_no',
        'program',
        'current_semester',
        'batch',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['program', 'current_semester', 'batch'])
            ->logOnlyDirty();
    }

    /**
     * The stored batch code (e.g. "F22") as a readable label (e.g. "Fall 2022").
     *
     * @return Attribute<string, never>
     */
    protected function batchLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! preg_match('/^([FS])(\d{2})$/', (string) $this->batch, $matches)) {
                    return $this->batch;
                }

                $term = $matches[1] === 'F' ? 'Fall' : 'Spring';

                return "{$term} 20{$matches[2]}";
            },
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Application, $this>
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }
}
