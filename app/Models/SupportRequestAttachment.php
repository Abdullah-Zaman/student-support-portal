<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A screenshot or PDF on a support request. Stored on the private disk
 * under a random name and served only through the authorized controller
 * to the request's reporter and the Super Admin.
 *
 * @property int $support_request_id
 * @property string $original_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $size
 */
class SupportRequestAttachment extends Model
{
    protected $fillable = [
        'support_request_id',
        'original_name',
        'file_path',
        'mime_type',
        'size',
    ];

    /**
     * @return BelongsTo<SupportRequest, $this>
     */
    public function supportRequest(): BelongsTo
    {
        return $this->belongsTo(SupportRequest::class);
    }
}
