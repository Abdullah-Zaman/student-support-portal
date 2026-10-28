<?php

namespace App\Actions\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Records a reply the Registrar sent by email (they have no portal
 * account) in the HOD's or Dean's private conversation with the
 * Registrar. It is logged by that portal participant and not emailed.
 * The caller authorizes first.
 */
class LogExternalReply
{
    public function __construct(private PostCorrespondence $post) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(CorrespondenceConversation $conversation, User $participant, string $body, string $idempotencyKey, array $files = []): CorrespondenceMessage
    {
        $party = CorrespondenceParty::forRole($participant->staffRole());
        $registrarForward = $conversation->messages()
            ->where('to_party', CorrespondenceParty::Registrar)
            ->latest('id')
            ->firstOrFail();

        return $this->post->handle($conversation->application, $participant, [
            'type' => CorrespondenceType::LoggedReply,
            'from_party' => CorrespondenceParty::Registrar,
            'from_user_id' => null,
            'from_email' => $registrarForward->to_email,
            'to_party' => $party,
            'to_user_id' => $participant->id,
            'body' => $body,
        ], $idempotencyKey, $files);
    }
}
