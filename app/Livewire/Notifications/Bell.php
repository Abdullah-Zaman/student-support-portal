<?php

namespace App\Livewire\Notifications;

use App\Enums\RoleName;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class Bell extends Component
{
    /**
     * "dark" for a plum header (student), "light" for a white one (admin).
     */
    public string $variant = 'dark';

    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->unreadNotifications()->count();
    }

    /**
     * Each role has its own notifications index route.
     */
    #[Computed]
    public function indexRouteName(): ?string
    {
        $user = Auth::user();

        $name = match (true) {
            $user->hasRole(RoleName::Student->value) => 'student.notifications.index',
            $user->hasRole(RoleName::AdminOfficer->value) => 'admin.notifications.index',
            $user->hasRole(RoleName::SuperAdmin->value) => 'super-admin.notifications.index',
            $user->hasRole(RoleName::Hod->value) => 'hod.notifications.index',
            $user->hasRole(RoleName::Dean->value) => 'dean.notifications.index',
            default => null,
        };

        return $name && Route::has($name) ? $name : null;
    }

    /**
     * @return Collection<int, DatabaseNotification>
     */
    #[Computed]
    public function latest(): Collection
    {
        return Auth::user()->notifications()->latest()->take(5)->get();
    }

    public function open(string $notificationId): void
    {
        $notification = Auth::user()->notifications()->findOrFail($notificationId);
        $notification->markAsRead();

        unset($this->unreadCount, $this->latest);
        $this->dispatch('notifications-updated');

        $this->redirect($notification->data['url'], navigate: true);
    }

    public function markAllAsRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();

        unset($this->unreadCount, $this->latest);
        $this->dispatch('notifications-updated');
    }

    #[On('notifications-updated')]
    public function refresh(): void
    {
        unset($this->unreadCount, $this->latest);
    }

    public function render(): View
    {
        return view('livewire.notifications.bell');
    }
}
