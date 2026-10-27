<?php

namespace App\Support\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inbox queries for the Admin Officer, HOD and Dean. A message is unread
 * until someone holding the recipient's role opens the thread (that also
 * shows it as "Seen" to the sender).
 */
class CorrespondenceInbox
{
    /**
     * Applications whose correspondence involves this staff member.
     *
     * @return Builder<Application>
     */
    public function applications(User $staff): Builder
    {
        return Application::query()->withCorrespondenceFor($staff);
    }

    /**
     * @return Builder<CorrespondenceMessage>
     */
    public function unreadMessages(User $staff): Builder
    {
        $party = CorrespondenceParty::forRole($staff->staffRole());

        if ($party === null) {
            return CorrespondenceMessage::query()->whereRaw('1 = 0');
        }

        return CorrespondenceMessage::query()
            ->where('to_party', $party)
            ->whereNull('seen_at')
            ->where(fn (Builder $query) => $query->whereNull('from_user_id')->orWhere('from_user_id', '!=', $staff->id))
            ->whereIn('application_id', $this->applications($staff)->select('applications.id'));
    }

    public function unreadCount(User $staff): int
    {
        return $this->unreadMessages($staff)->count();
    }

    /**
     * Keep applications where the latest message involving the party was
     * sent to it (awaiting its reply) or by it (awaiting the other side).
     *
     * @param  Builder<Application>  $applications
     * @return Builder<Application>
     */
    public function whereAwaiting(Builder $applications, CorrespondenceParty $party, bool $awaitingParty): Builder
    {
        $latestInvolving = CorrespondenceMessage::query()
            ->from('correspondence_messages as latest')
            ->select('latest.id')
            ->whereColumn('latest.application_id', 'applications.id')
            ->where(fn (Builder $query) => $query->where('latest.from_party', $party)->orWhere('latest.to_party', $party))
            ->orderByDesc('latest.id')
            ->limit(1);

        return $applications->whereHas('correspondence', fn (Builder $query) => $query
            ->where('correspondence_messages.id', $latestInvolving)
            ->where($awaitingParty ? 'to_party' : 'from_party', $party));
    }
}
