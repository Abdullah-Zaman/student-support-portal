<?php

namespace App\Actions\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * A message inside a private portal conversation (Admin Officer, HOD or
 * Dean). Whether the sender may write in it is the conversation policy's
 * job (reply ability); the caller authorizes first.
 */
class ReplyToCorrespondence
{
    public function __construct(private PostCorrespondence $post) {}

    /**
     * @param  array<int, UploadedFile>  $files
     *
     * @throws ValidationException when there is no one holding the other side's role
     */
    public function handle(CorrespondenceConversation $conversation, User $sender, string $body, string $idempotencyKey, array $files = []): CorrespondenceMessage
    {
        $from = CorrespondenceParty::forRole($sender->staffRole());

        if ($from === null) {
            throw ValidationException::withMessages(['reply_body' => __('Only the Admin Officer, HOD or Dean can reply.')]);
        }

        $to = $conversation->counterpartOf($from);
        $application = $conversation->application;
        $recipient = $this->post->recipientFor($application, $to, $from);

        if ($recipient === null) {
            throw ValidationException::withMessages(['reply_body' => __('There is no active :party to reply to.', ['party' => $to->label()])]);
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
