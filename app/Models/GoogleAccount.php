<?php

namespace App\Models;

use Database\Factories\GoogleAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A staff member's connected Gmail, used to send forwards from their own
 * account (gmail.send scope only). Tokens are encrypted at rest; no
 * Google password is ever stored.
 *
 * @property int $id
 * @property int $user_id
 * @property string $google_email
 * @property string|null $access_token
 * @property string $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $scopes
 * @property bool $needs_reconnect
 * @property Carbon $connected_at
 */
class GoogleAccount extends Model
{
    /** @use HasFactory<GoogleAccountFactory> */
    use HasFactory;

    public const GMAIL_SEND_SCOPE = 'https://www.googleapis.com/auth/gmail.send';

    protected $fillable = [
        'user_id',
        'google_email',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'needs_reconnect',
        'connected_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'needs_reconnect' => 'boolean',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether the stored access token can still be used for a minute.
     */
    public function hasFreshAccessToken(): bool
    {
        return $this->access_token !== null
            && $this->token_expires_at !== null
            && $this->token_expires_at->isAfter(now()->addMinute());
    }
}
