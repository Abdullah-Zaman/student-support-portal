<?php

namespace App\Models;

use App\Models\Concerns\ScopedToAdminDepartment;
use App\Support\RegistrationNumber;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory, LogsActivity, ScopedToAdminDepartment;

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => RegistrationNumber::forgetProgramCodes());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['department_id', 'code', 'name', 'is_active'])
            ->logOnlyDirty();
    }

    /**
     * Programs a new student can register for: the program, its
     * department and that department's faculty must all be active.
     *
     * @param  Builder<Program>  $query
     * @return Builder<Program>
     */
    public function scopeOpenForRegistration(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereHas('department', fn (Builder $query) => $query->where('is_active', true)
                ->whereHas('faculty', fn (Builder $query) => $query->where('is_active', true)));
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
