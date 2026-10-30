<?php

namespace App\Livewire\Admin\Timetables;

use App\Enums\RoleName;
use App\Models\TimetableShareLink;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Manage the department's public timetable link. Every action
 * re-authorizes through the policy.
 */
class ShareCard extends Component
{
    /**
     * Super Admin only: the department chosen on the page. An Admin
     * Officer's own department always wins over this value.
     */
    #[Reactive]
    public string $departmentId = '';

    private function authorizeManage(): int
    {
        $user = Auth::user();
        $departmentId = $user->hasRole(RoleName::SuperAdmin->value)
            ? ($this->departmentId !== '' ? (int) $this->departmentId : null)
            : $user->department_id;

        $this->authorize('manage', [TimetableShareLink::class, $departmentId]);

        return $departmentId;
    }

    public function mount(): void
    {
        $user = Auth::user();

        abort_unless($user->hasAnyRole([RoleName::AdminOfficer->value, RoleName::SuperAdmin->value]), 403);
    }

    #[Computed]
    public function link(): ?TimetableShareLink
    {
        return TimetableShareLink::query()->where('department_id', $this->authorizeManage())->first();
    }

    public function generate(): void
    {
        $departmentId = $this->authorizeManage();
        $existing = TimetableShareLink::query()->where('department_id', $departmentId)->first();

        if ($existing === null) {
            $link = TimetableShareLink::create([
                'department_id' => $departmentId,
                'token' => TimetableShareLink::newToken(),
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);
            $event = 'created';
        } else {
            $existing->update(['token' => TimetableShareLink::newToken(), 'is_active' => true]);
            $link = $existing;
            $event = 'regenerated';
        }

        $this->record($link, $event);
        unset($this->link);

        Flux::toast(variant: 'success', text: $event === 'created' ? __('Share link created.') : __('New link generated. The old link no longer works.'));
        Flux::modal('regenerate-share-link')->close();
    }

    public function toggleActive(): void
    {
        $link = $this->link();
        abort_if($link === null, 404);

        $link->update(['is_active' => ! $link->is_active]);
        $this->record($link, $link->is_active ? 'activated' : 'deactivated');
        unset($this->link);

        Flux::toast(variant: 'success', text: $link->is_active ? __('Share link activated.') : __('Share link deactivated.'));
        Flux::modal('toggle-share-link')->close();
    }

    private function record(TimetableShareLink $link, string $event): void
    {
        activity()
            ->performedOn($link)
            ->causedBy(Auth::user())
            ->withProperties(['department_id' => $link->department_id, 'is_active' => $link->is_active])
            ->event($event)
            ->log('Timetable share link '.$event);
    }

    public function render(): View
    {
        $this->authorizeManage();

        return view('livewire.admin.timetables.share-card');
    }
}
