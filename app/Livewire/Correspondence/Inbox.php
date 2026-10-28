<?php

namespace App\Livewire\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Enums\RoleName;
use App\Models\CorrespondenceConversation;
use App\Support\Correspondence\CorrespondenceInbox;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The staff inbox for the Admin Officer, HOD and Dean: their own private
 * conversations (not applications), unread first, with Unread / Awaiting
 * my reply / Awaiting their reply filters. The Super Admin has none.
 */
#[Title('Inbox')]
class Inbox extends Component
{
    use WithPagination;

    public const FILTERS = ['unread', 'awaiting_me', 'awaiting_them'];

    #[Url]
    public string $filter = '';

    public function mount(): void
    {
        abort_if($this->party() === null, 403);
    }

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, CorrespondenceConversation>
     */
    #[Computed]
    public function conversations(): LengthAwarePaginator
    {
        $user = Auth::user();
        $party = $this->party();
        $unread = fn (Builder $query) => CorrespondenceInbox::constrainToUnread($query, $party, $user);

        $query = app(CorrespondenceInbox::class)->conversations($user)
            ->with(['application.student.user', 'latestMessage.sender'])
            ->withCount(['messages as unread_count' => $unread])
            ->withMax('messages as latest_message_id', 'id');

        $query = match ($this->filter) {
            'unread' => $query->whereHas('messages', $unread),
            'awaiting_me' => $query->whereHas('latestMessage', fn (Builder $latest) => $latest->where('to_party', $party)),
            'awaiting_them' => $query->whereHas('latestMessage', fn (Builder $latest) => $latest->where('from_party', $party)),
            default => $query,
        };

        return $query
            ->orderByDesc('unread_count')
            ->orderByDesc('latest_message_id')
            ->paginate(15);
    }

    public function conversationUrl(CorrespondenceConversation $conversation): string
    {
        return route((string) $this->party()?->applicationRouteName(), $conversation->application).'?conversation='.$conversation->id.'#correspondence';
    }

    public function render(): View
    {
        return view('livewire.correspondence.inbox');
    }

    private function party(): ?CorrespondenceParty
    {
        $role = Auth::user()->staffRole();

        return $role === RoleName::SuperAdmin ? null : CorrespondenceParty::forRole($role);
    }
}
