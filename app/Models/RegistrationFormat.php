<?php

namespace App\Models;

use Database\Factories\RegistrationFormatFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An additional student registration format the Super Admin allows, for
 * old or pass-out students whose email and registration number differ
 * from the current program-based format. Both patterns are templates
 * (see FormatTemplate), never raw regex. The email template is the part
 * before "@superior.edu.pk": the domain is locked in code.
 *
 * @property string $name
 * @property string $email_template
 * @property string $registration_no_template
 * @property string|null $program_code
 * @property string|null $batch
 * @property bool $is_alumni
 * @property bool $is_active
 */
class RegistrationFormat extends Model
{
    /** @use HasFactory<RegistrationFormatFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'email_template',
        'registration_no_template',
        'program_code',
        'batch',
        'is_alumni',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_alumni' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
