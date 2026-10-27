<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file on a correspondence message. Stored on the private disk under a
 * random name and served only through the authorized controller, to
 * staff who may view the application's correspondence.
 *
 * @property int $correspondence_message_id
 * @property int $application_id
 * @property int $uploaded_by
 * @property string $original_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $size
 */
class CorrespondenceAttachment extends Model
{
    protected $fillable = [
        'correspondence_message_id',
        'application_id',
        'uploaded_by',
        'original_name',
        'file_path',
        'mime_type',
        'size',
    ];

    /**
     * @return BelongsTo<CorrespondenceMessage, $this>
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(CorrespondenceMessage::class, 'correspondence_message_id');
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
