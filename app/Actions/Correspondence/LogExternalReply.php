<?php

namespace App\Actions\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Records a reply the Registrar sent by email (they have no portal
 * account). It answers the latest forward to the Registrar, so it is
 * addressed to whoever sent that forward, and is marked as logged by the
 * Admin Officer. It is not emailed. The caller authorizes first.
 */
class LogExternalReply
{
    public function __construct(private PostCorrespondence $post) {}

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(Application $application, User $admin, string $body, string $idempotencyKey, array $files = []): CorrespondenceMessage
    {
        $registrarForward = $application->correspondence()
            ->where('to_party', CorrespondenceParty::Registrar)
            ->latest('id')
            ->firstOrFail();

        return $this->post->handle($application, $admin, [
            'type' => CorrespondenceType::LoggedReply,
            'from_party' => CorrespondenceParty::Registrar,
            'from_user_id' => null,
            'from_email' => $registrarForward->to_email,
            'to_party' => $registrarForward->from_party,
            'to_user_id' => $registrarForward->from_user_id,
            'body' => $body,
        ], $idempotencyKey, $files);
    }
}
