<?php

namespace App\Support\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Inbox queries for the Admin Officer, HOD and Dean over their own
 * private conversations. A message is unread until someone holding the
 * recipient's role opens its conversation (that also shows it as "Seen"
 * to the sender).
 */
class CorrespondenceInbox
{
    /**
     * @return Builder<CorrespondenceConversation>
     */
    public function conversations(User $staff): Builder
    {
        return CorrespondenceConversation::query()->visibleTo($staff);
    }

    /**
     * Messages to the staff member's party that they have not opened.
     *
     * @return Builder<CorrespondenceMessage>
     */
    public function unreadMessages(User $staff): Builder
    {
        $party = CorrespondenceParty::forRole($staff->staffRole());

        if ($party === null) {
            return CorrespondenceMessage::query()->whereRaw('1 = 0');
        }

        return self::constrainToUnread(CorrespondenceMessage::query()->visibleTo($staff), $party, $staff);
    }

    public function unreadCount(User $staff): int
    {
        return $this->unreadMessages($staff)->count();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function constrainToUnread(Builder $query, CorrespondenceParty $party, User $staff): Builder
    {
        return $query
            ->where('to_party', $party)
            ->whereNull('seen_at')
            ->where(fn (Builder $query) => $query->whereNull('from_user_id')->orWhere('from_user_id', '!=', $staff->id));
    }
}
