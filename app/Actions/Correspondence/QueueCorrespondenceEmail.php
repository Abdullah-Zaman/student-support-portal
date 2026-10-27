<?php

namespace App\Actions\Correspondence;

use App\Enums\CorrespondenceEmailStatus;
use App\Jobs\SendCorrespondenceEmail;
use App\Models\CorrespondenceMessage;
use App\Models\User;

/**
 * Queues a failed or Gmail-pending message's email again. The status
 * moves to "sending" with one conditional update, so a double click (or
 * a retry racing the automatic send after connecting Gmail) queues it
 * only once.
 */
class QueueCorrespondenceEmail
{
    /**
     * Returns false when the message was not failed or pending any more.
     */
    public function handle(CorrespondenceMessage $message): bool
    {
        $claimed = CorrespondenceMessage::query()
            ->whereKey($message->id)
            ->whereIn('email_status', [CorrespondenceEmailStatus::Failed, CorrespondenceEmailStatus::AwaitingGmail])
            ->update(['email_status' => CorrespondenceEmailStatus::Sending, 'email_error' => null]);

        if ($claimed === 0) {
            return false;
        }

        $message->refresh();

        activity()
            ->causedBy($message->sender)
            ->performedOn($message)
            ->withProperties(['application_id' => $message->application_id])
            ->log('correspondence email queued');

        SendCorrespondenceEmail::dispatch($message);

        return true;
    }

    /**
     * Everything the user wrote while their Gmail was not connected; run
     * when they connect or reconnect it. Returns how many were queued.
     */
    public function pendingFor(User $user): int
    {
        return CorrespondenceMessage::query()
            ->where('author_id', $user->id)
            ->where('from_user_id', $user->id)
            ->where('email_status', CorrespondenceEmailStatus::AwaitingGmail)
            ->orderBy('id')
            ->get()
            ->filter(fn (CorrespondenceMessage $message) => $this->handle($message))
            ->count();
    }
}
