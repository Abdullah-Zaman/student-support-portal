<?php

namespace App\Models;

use App\Enums\TimetableClassType;
use Carbon\CarbonInterface;
use Database\Factories\TimetableClassFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $day ISO day of the week, 1 (Monday) to 7 (Sunday)
 * @property string $starts_at
 * @property string $ends_at
 * @property string $room
 * @property string|null $floor
 * @property string $course
 * @property TimetableClassType $type
 * @property string|null $teacher
 * @property string $raw_sections
 */
class TimetableClass extends Model
{
    /** @use HasFactory<TimetableClassFactory> */
    use HasFactory;

    public const DAY_NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    /**
     * Soft background, text and border colours, picked per course so the
     * same course always reads the same across the week.
     *
     * @var list<array{bg: string, text: string, border: string}>
     */
    private const COURSE_COLORS = [
        ['bg' => '#F4ECF2', 'text' => '#5B3550', 'border' => '#E3CFDD'],
        ['bg' => '#E3EEFB', 'text' => '#1D4F8A', 'border' => '#C4DAF3'],
        ['bg' => '#E2F3E8', 'text' => '#1F6A3A', 'border' => '#C2E3CD'],
        ['bg' => '#FFF1D6', 'text' => '#7A4A00', 'border' => '#F3DDAE'],
        ['bg' => '#EFE7F7', 'text' => '#5B3A86', 'border' => '#DACCEB'],
        ['bg' => '#FCEBEA', 'text' => '#8A1C14', 'border' => '#F3CDCA'],
        ['bg' => '#E4F4F4', 'text' => '#1E5F60', 'border' => '#C3E4E4'],
        ['bg' => '#FBEFE4', 'text' => '#7C3D12', 'border' => '#F1D6BF'],
    ];

    protected $fillable = [
        'timetable_version_id',
        'day',
        'starts_at',
        'ends_at',
        'room',
        'floor',
        'course',
        'type',
        'teacher',
        'raw_sections',
        'sheet',
        'cell',
    ];

    protected function casts(): array
    {
        return [
            'day' => 'integer',
            'type' => TimetableClassType::class,
        ];
    }

    /**
     * @param  Builder<TimetableClass>  $query
     * @return Builder<TimetableClass>
     */
    public function scopeForSection(Builder $query, string $section): Builder
    {
        return $query->whereHas('sections', fn (Builder $query) => $query->where('section', $section));
    }

    /**
     * @param  Builder<TimetableClass>  $query
     * @return Builder<TimetableClass>
     */
    public function scopeInWeekOrder(Builder $query): Builder
    {
        return $query->orderBy('day')->orderBy('starts_at')->orderBy('room');
    }

    public function dayName(): string
    {
        return self::DAY_NAMES[$this->day] ?? '';
    }

    /**
     * Start time on the given date (defaults to today), in the app timezone.
     */
    public function startsOn(?CarbonInterface $date = null): CarbonInterface
    {
        return ($date ?? now())->copy()->setTimeFromTimeString($this->starts_at);
    }

    public function endsOn(?CarbonInterface $date = null): CarbonInterface
    {
        return ($date ?? now())->copy()->setTimeFromTimeString($this->ends_at);
    }

    /**
     * e.g. "8:00 AM – 10:40 AM".
     */
    public function timeLabel(): string
    {
        return $this->startsOn()->format('g:i A').' – '.$this->endsOn()->format('g:i A');
    }

    /**
     * The course name without a trailing "(Theory)" or "(Lab)", so a
     * course's theory and lab classes share one colour.
     */
    public function baseCourseName(): string
    {
        return trim((string) preg_replace('/\s*\([^)]*\)\s*$/', '', $this->course));
    }

    /**
     * @return array{bg: string, text: string, border: string}
     */
    public function courseColor(): array
    {
        $key = mb_strtolower($this->baseCourseName());

        return self::COURSE_COLORS[crc32($key) % count(self::COURSE_COLORS)];
    }

    /**
     * @return BelongsTo<TimetableVersion, $this>
     */
    public function version(): BelongsTo
    {
        return $this->belongsTo(TimetableVersion::class, 'timetable_version_id');
    }

    /**
     * @return HasMany<TimetableClassSection, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(TimetableClassSection::class);
    }
}
