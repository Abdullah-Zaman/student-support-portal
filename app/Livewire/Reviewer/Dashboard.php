<?php

namespace App\Livewire\Reviewer;

use App\Enums\RoleName;
use App\Models\Application;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * HOD/Dean dashboard: what is waiting for their remark and what they
 * have already handled. Only applications forwarded to them count.
 */
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Application::class);
    }

    /**
     * @return array{forwarded: int, awaiting: int, remarked: int, onward: int}
     */
    #[Computed]
    public function counts(): array
    {
        $role = $this->role();

        return [
            'forwarded' => $this->inbox()->count(),
            'awaiting' => $this->inbox()->whereDoesntHave('remarks', fn (Builder $query) => $query->where('author_role', $role))->count(),
            'remarked' => $this->inbox()->whereHas('remarks', fn (Builder $query) => $query->where('author_role', $role))->count(),
            'onward' => $this->inbox()->whereHas('forwards', fn (Builder $query) => $query->where('from_role', $role))->count(),
        ];
    }

    /**
     * @return Collection<int, Application>
     */
    #[Computed]
    public function recent(): Collection
    {
        return $this->inbox()
            ->with(['category', 'student.user'])
            ->withMax(['forwards as forwarded_at' => fn (Builder $query) => $query->where('to_role', $this->role())], 'created_at')
            ->orderByDesc('forwarded_at')
            ->take(5)
            ->get();
    }

    public function routePrefix(): string
    {
        return $this->role().'.';
    }

    /**
     * @return Builder<Application>
     */
    private function inbox(): Builder
    {
        return Application::query()->forwardedTo(Auth::user());
    }

    private function role(): string
    {
        return Auth::user()->hasRole(RoleName::Dean->value) ? RoleName::Dean->value : RoleName::Hod->value;
    }

    public function render(): View
    {
        return view('livewire.reviewer.dashboard');
    }
}
