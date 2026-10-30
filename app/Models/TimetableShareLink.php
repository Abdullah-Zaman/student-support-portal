<?php

namespace App\Models;

use Database\Factories\TimetableShareLinkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The one public, read-only timetable link of a department. The token is
 * the only secret: replacing it kills the old link immediately.
 *
 * @property int $department_id
 * @property string $token
 * @property bool $is_active
 * @property int $created_by
 */
class TimetableShareLink extends Model
{
    /** @use HasFactory<TimetableShareLinkFactory> */
    use HasFactory;

    public const TOKEN_LENGTH = 48;

    protected $fillable = ['department_id', 'token', 'is_active', 'created_by'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function newToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }

    /**
     * The department's link for a token, only while it is active.
     */
    public static function activeForToken(string $token): ?self
    {
        return self::query()
            ->where('token', $token)
            ->where('is_active', true)
            ->first();
    }

    public function url(): string
    {
        return route('public.timetable', ['token' => $this->token]);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
