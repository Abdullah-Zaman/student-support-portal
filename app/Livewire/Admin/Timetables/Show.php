<?php

namespace App\Livewire\Admin\Timetables;

use App\Actions\Timetable\PublishTimetableVersion;
use App\Enums\RoleName;
use App\Models\Scopes\AdminDepartmentScope;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Timetable Preview')]
class Show extends Component
{
    #[Locked]
    public TimetableVersion $timetableVersion;

    public string $day = '';

    public string $section = '';

    /**
     * Loaded without the admin-department scope, so another department's
     * version is denied by the policy with a 403 instead of a 404.
     */
    public function mount(int $version): void
    {
        $timetableVersion = TimetableVersion::withoutGlobalScope(AdminDepartmentScope::class)->findOrFail($version);

        $this->authorize('view', $timetableVersion);

        $this->timetableVersion = $timetableVersion->load(['department', 'uploader', 'publisher']);
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return Auth::user()->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * @return array{classes: int, rooms: int, teachers: int, unparsed: int}
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'classes' => $this->timetableVersion->class_count,
            'rooms' => $this->timetableVersion->classes()->distinct()->count('room'),
            'teachers' => $this->timetableVersion->classes()->whereNotNull('teacher')->distinct()->count('teacher'),
            'unparsed' => count($this->timetableVersion->unparsed_cells ?? []),
        ];
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function sectionOptions(): array
    {
        return $this->timetableVersion->sectionNames();
    }

    /**
     * @return Collection<int, TimetableClass>
     */
    #[Computed]
    public function classes(): Collection
    {
        return $this->timetableVersion->classes()
            ->with('sections')
            ->when($this->day !== '', fn ($query) => $query->where('day', (int) $this->day))
            ->when($this->section !== '', fn ($query) => $query->forSection($this->section))
            ->inWeekOrder()
            ->get();
    }

    public function publish(PublishTimetableVersion $publishTimetableVersion): void
    {
        $this->authorize('publish', $this->timetableVersion);

        $publishTimetableVersion->handle($this->timetableVersion, Auth::user());

        $this->timetableVersion->refresh();
        $this->modal('publish-version')->close();
        Flux::toast(variant: 'success', text: __('Version :number is now the published timetable.', ['number' => $this->timetableVersion->version_no]));
    }

    public function routePrefix(): string
    {
        return $this->isSuperAdmin() ? 'super-admin.' : 'admin.';
    }

    public function render(): View
    {
        return view('livewire.admin.timetables.show');
    }
}
