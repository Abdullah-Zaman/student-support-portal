<?php

namespace App\Actions\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * A reply inside the portal between the Admin Officer, HOD and Dean.
 * Which pairs are allowed is the ApplicationPolicy's job (reply ability);
 * the caller authorizes first.
 */
class ReplyToCorrespondence
{
    public function __construct(private PostCorrespondence $post) {}

    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws ValidationException when there is no one holding the recipient's role
     */
    public function handle(Application $application, User $sender, CorrespondenceParty $to, string $body, string $idempotencyKey, array $files = []): CorrespondenceMessage
    {
        $from = CorrespondenceParty::forRole($sender->staffRole());

        if ($from === null) {
            throw ValidationException::withMessages(['reply_to' => __('Only the Admin Officer, HOD or Dean can reply.')]);
        }

        $recipient = $this->post->recipientFor($application, $to, $from);

        if ($recipient === null) {
            throw ValidationException::withMessages(['reply_to' => __('There is no active :party to reply to.', ['party' => $to->label()])]);
        }

        return $this->post->handle($application, $sender, [
            'type' => CorrespondenceType::Reply,
            'from_party' => $from,
            'from_user_id' => $sender->id,
            'to_party' => $to,
            'to_user_id' => $recipient->id,
            'body' => $body,
        ], $idempotencyKey, $files);
    }
}
