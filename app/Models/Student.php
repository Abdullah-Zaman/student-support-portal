<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAdminDepartmentViaUser;
use App\Models\Scopes\AdminDepartmentScope;
use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $current_semester
 * @property string|null $section
 */
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory, LogsActivity, ScopedToAdminDepartmentViaUser;

    protected $fillable = [
        'user_id',
        'registration_no',
        'program_id',
        'current_semester',
        'batch',
        'section',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['program_id', 'current_semester', 'batch', 'section'])
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
            get: fn () => self::labelForBatch((string) $this->batch),
        );
    }

    /**
     * "F22" becomes "Fall 2022", "S23" becomes "Spring 2023". Anything
     * else is returned unchanged.
     */
    public static function labelForBatch(string $batch): string
    {
        if (! preg_match('/^([FS])(\d{2})$/', $batch, $matches)) {
            return $batch;
        }

        $term = $matches[1] === 'F' ? 'Fall' : 'Spring';

        return "{$term} 20{$matches[2]}";
    }

    /**
     * Unscoped: anyone allowed to see the student may see their program,
     * even if a Super Admin has since moved it to another department.
     *
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withoutGlobalScope(AdminDepartmentScope::class);
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
