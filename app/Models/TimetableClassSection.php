<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One expanded section (e.g. "BSSE-3A") that attends a timetable class.
 *
 * @property string $section
 * @property string $program_code
 * @property int $semester
 */
class TimetableClassSection extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'timetable_class_id',
        'section',
        'program_code',
        'semester',
    ];

    /**
     * @return BelongsTo<TimetableClass, $this>
     */
    public function timetableClass(): BelongsTo
    {
        return $this->belongsTo(TimetableClass::class);
    }
}
