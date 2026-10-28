<?php

namespace App\Livewire\SuperAdmin\Support;

use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every support request, newest first, for the Super Admin, with status
 * and category filters. Open ones are counted in the sidebar.
 */
#[Title('Support requests')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    #[Url]
    public string $category = '';

    public function mount(): void
    {
        $this->authorize('manage', SupportRequest::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'category'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['status', 'category']);
        $this->resetPage();
    }

    /**
     * @return LengthAwarePaginator<int, SupportRequest>
     */
    #[Computed]
    public function requests(): LengthAwarePaginator
    {
        return SupportRequest::query()
            ->with('reporter')
            ->when(SupportRequestStatus::tryFrom($this->status) !== null, fn (Builder $query) => $query->where('status', $this->status))
            ->when(SupportRequestCategory::tryFrom($this->category) !== null, fn (Builder $query) => $query->where('category', $this->category))
            ->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")
            ->latest('id')
            ->paginate(15);
    }

    public function render(): View
    {
        return view('livewire.super-admin.support.index');
    }
}
