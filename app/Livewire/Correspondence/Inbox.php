<?php

namespace App\Livewire\Correspondence;

use App\Enums\CorrespondenceParty;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
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
 * The staff correspondence inbox for the Admin Officer, HOD and Dean:
 * applications whose correspondence involves them, unread first, with
 * Unread / Awaiting my reply / Awaiting their reply filters.
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
     * @return LengthAwarePaginator<int, Application>
     */
    #[Computed]
    public function applications(): LengthAwarePaginator
    {
        $user = Auth::user();
        $party = $this->party();
        $inbox = app(CorrespondenceInbox::class);
        $unread = fn (Builder $query) => $query
            ->where('to_party', $party)
            ->whereNull('seen_at')
            ->where(fn (Builder $query) => $query->whereNull('from_user_id')->orWhere('from_user_id', '!=', $user->id));

        $query = $inbox->applications($user)
            ->with(['student.user', 'latestCorrespondence.sender'])
            ->withCount(['correspondence as unread_count' => $unread])
            ->withMax('correspondence as latest_correspondence_id', 'id');

        $query = match ($this->filter) {
            'unread' => $query->whereHas('correspondence', $unread),
            'awaiting_me' => $inbox->whereAwaiting($query, $party, awaitingParty: true),
            'awaiting_them' => $inbox->whereAwaiting($query, $party, awaitingParty: false),
            default => $query,
        };

        return $query
            ->orderByDesc('unread_count')
            ->orderByDesc('latest_correspondence_id')
            ->paginate(15);
    }

    public function applicationUrl(Application $application): string
    {
        return route((string) $this->party()?->applicationRouteName(), $application).'#correspondence';
    }

    public function isFromMe(?CorrespondenceMessage $message): bool
    {
        return $message !== null && $message->from_party === $this->party();
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
