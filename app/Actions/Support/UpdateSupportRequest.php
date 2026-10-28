<?php

namespace App\Actions\Support;

use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\SupportRequestUpdated;

/**
 * The Super Admin's actions on a support request: set it in progress,
 * reply with a note, or resolve it with a resolution note. Each one is
 * logged (metadata only) and tells the reporter. The caller authorizes
 * first; a resolved request is final.
 */
class UpdateSupportRequest
{
    public function markInProgress(SupportRequest $supportRequest, User $actor, ?string $note = null): SupportRequest
    {
        if ($supportRequest->status !== SupportRequestStatus::Open) {
            return $supportRequest;
        }

        $supportRequest->update([
            'status' => SupportRequestStatus::InProgress,
            'resolution_note' => $this->clean($note) ?? $supportRequest->resolution_note,
        ]);

        $this->logStatusChange($supportRequest, $actor, SupportRequestStatus::Open, SupportRequestStatus::InProgress);
        $supportRequest->reporter->notify(new SupportRequestUpdated($supportRequest, SupportRequestUpdated::IN_PROGRESS));

        return $supportRequest;
    }

    public function reply(SupportRequest $supportRequest, User $actor, string $note): SupportRequest
    {
        $supportRequest->update(['resolution_note' => trim($note)]);

        activity()
            ->causedBy($actor)
            ->performedOn($supportRequest)
            ->withProperties(['status' => $supportRequest->status->value])
            ->log('support request replied');

        $supportRequest->reporter->notify(new SupportRequestUpdated($supportRequest, SupportRequestUpdated::REPLIED));

        return $supportRequest;
    }

    public function resolve(SupportRequest $supportRequest, User $actor, string $note): SupportRequest
    {
        $from = $supportRequest->status;

        $supportRequest->update([
            'status' => SupportRequestStatus::Resolved,
            'resolution_note' => trim($note),
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
        ]);

        activity()
            ->causedBy($actor)
            ->performedOn($supportRequest)
            ->withProperties(['from' => $from->value, 'to' => SupportRequestStatus::Resolved->value])
            ->log('support request resolved');

        $supportRequest->reporter->notify(new SupportRequestUpdated($supportRequest, SupportRequestUpdated::RESOLVED));

        return $supportRequest;
    }

    private function logStatusChange(SupportRequest $supportRequest, User $actor, SupportRequestStatus $from, SupportRequestStatus $to): void
    {
        activity()
            ->causedBy($actor)
            ->performedOn($supportRequest)
            ->withProperties(['from' => $from->value, 'to' => $to->value])
            ->log('support request status changed');
    }

    private function clean(?string $note): ?string
    {
        return $note !== null && trim($note) !== '' ? trim($note) : null;
    }
}
