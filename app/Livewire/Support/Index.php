<?php

namespace App\Livewire\Support;

use App\Models\SupportRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * A staff member's own support requests (never anyone else's), with the
 * status and note of each.
 */
#[Title('Contact Super Admin')]
class Index extends Component
{
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('create', SupportRequest::class);
    }

    /**
     * @return LengthAwarePaginator<int, SupportRequest>
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return SupportRequest::query()
            ->reportedBy(Auth::user())
            ->latest('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.support.index');
    }
}
