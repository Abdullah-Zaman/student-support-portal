<?php

namespace App\Actions\Forwarding;

use App\Actions\Correspondence\PostCorrespondence;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\RemarkType;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use App\Models\User;

/**
 * Records an HOD's or Dean's remark (Recommended / Not recommended /
 * Comment) as a message to the Admin Officer in their private
 * conversation. The caller authorizes first. Remarks never reach the
 * student or anyone outside that conversation.
 */
class AddRemark
{
    public function __construct(private PostCorrespondence $post) {}

    public function handle(Application $application, User $author, RemarkType $type, ?string $body, string $idempotencyKey): CorrespondenceMessage
    {
        $fromParty = CorrespondenceParty::forRole($author->staffRole()) ?? CorrespondenceParty::Hod;
        $adminOfficer = $this->post->recipientFor($application, CorrespondenceParty::AdminOfficer, $fromParty);

        return $this->post->handle($application, $author, [
            'type' => CorrespondenceType::Remark,
            'from_party' => $fromParty,
            'from_user_id' => $author->id,
            'to_party' => CorrespondenceParty::AdminOfficer,
            'to_user_id' => $adminOfficer?->id,
            'remark_type' => $type,
            'body' => $body,
        ], $idempotencyKey);
    }
}
