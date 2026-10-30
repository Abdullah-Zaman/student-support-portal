<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Models\Concerns\ScopedToAdminDepartment;
use Database\Factories\OfficeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A university office the Admin Officer forwards applications to by
 * email (Accounts, Exams, ...). Never deleted, only deactivated.
 *
 * @property int $department_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 * @property bool $available_to_hod
 * @property bool $available_to_dean
 */
class Office extends Model
{
    /** @use HasFactory<OfficeFactory> */
    use HasFactory, LogsActivity, ScopedToAdminDepartment;

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'is_active',
        'available_to_hod',
        'available_to_dean',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'available_to_hod' => 'boolean',
            'available_to_dean' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['department_id', 'name', 'code', 'is_active', 'available_to_hod', 'available_to_dean'])
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
     * @return HasMany<OfficeContact, $this>
     */
    public function contacts(): HasMany
    {
        return $this->hasMany(OfficeContact::class);
    }

    /**
     * Active offices of a department that this user may pick as a forward
     * recipient: the Admin Officer sees all of them, the HOD and Dean only
     * those switched on for them, anyone else none.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSelectableBy(Builder $query, User $user, int $departmentId): Builder
    {
        $query->withoutGlobalScopes()
            ->where('department_id', $departmentId)
            ->where('is_active', true);

        return match ($user->staffRole()) {
            RoleName::AdminOfficer => $query,
            RoleName::Hod => $query->where('available_to_hod', true),
            RoleName::Dean => $query->where('available_to_dean', true),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
