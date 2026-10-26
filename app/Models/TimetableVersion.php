<?php

namespace App\Models;

use App\Enums\TimetableVersionStatus;
use App\Models\Concerns\ScopedToAdminDepartment;
use Database\Factories\TimetableVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $department_id
 * @property int $version_no
 * @property string $original_name
 * @property string $file_path
 * @property TimetableVersionStatus $status
 * @property int $class_count
 * @property list<array{sheet: string, cell: string, text: string, reason: string}>|null $unparsed_cells
 * @property Carbon|null $published_at
 */
class TimetableVersion extends Model
{
    /** @use HasFactory<TimetableVersionFactory> */
    use HasFactory, LogsActivity, ScopedToAdminDepartment;

    protected $fillable = [
        'department_id',
        'version_no',
        'uploaded_by',
        'original_name',
        'file_path',
        'status',
        'class_count',
        'unparsed_cells',
        'published_at',
        'published_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => TimetableVersionStatus::class,
            'unparsed_cells' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['version_no', 'original_name', 'status', 'class_count', 'published_at'])
            ->logOnlyDirty();
    }

    /**
     * The one version of a department that students currently see.
     */
    public static function publishedForDepartment(?int $departmentId): ?self
    {
        if ($departmentId === null) {
            return null;
        }

        return self::query()
            ->where('department_id', $departmentId)
            ->where('status', TimetableVersionStatus::Published)
            ->first();
    }

    /**
     * Every distinct section in this version, optionally limited to one
     * program and semester, ordered by semester then name.
     *
     * @return list<string>
     */
    public function sectionNames(?string $programCode = null, ?int $semester = null): array
    {
        /** @var list<string> $sections */
        $sections = TimetableClassSection::query()
            ->whereIn('timetable_class_id', $this->classes()->select('id'))
            ->when($programCode !== null, fn (Builder $query) => $query->where('program_code', $programCode))
            ->when($semester !== null, fn (Builder $query) => $query->where('semester', $semester))
            ->select(['section', 'semester'])
            ->distinct()
            ->orderBy('semester')
            ->orderBy('section')
            ->pluck('section')
            ->values()
            ->all();

        return $sections;
    }

    /**
     * Number of classes per ISO day (1 = Monday) for the preview page.
     *
     * @return array<int, int>
     */
    public function classCountsByDay(): array
    {
        /** @var array<int, int> $counts */
        $counts = $this->classes()
            ->selectRaw('day, count(*) as aggregate')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('aggregate', 'day')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        return $counts;
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /**
     * @return HasMany<TimetableClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(TimetableClass::class);
    }

    /**
     * @return HasManyThrough<TimetableClassSection, TimetableClass, $this>
     */
    public function sections(): HasManyThrough
    {
        return $this->hasManyThrough(TimetableClassSection::class, TimetableClass::class);
    }
}
