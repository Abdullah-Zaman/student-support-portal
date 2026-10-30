<?php

namespace App\Livewire\Admin\Announcements;

use App\Concerns\AnnouncementValidationRules;
use App\Enums\RoleName;
use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgement;
use App\Models\Department;
use App\Models\Scopes\AdminDepartmentScope;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Announcements')]
class Index extends Component
{
    use AnnouncementValidationRules, WithFileUploads;

    public ?int $editing_id = null;

    public string $title = '';

    public string $body = '';

    public mixed $image = null;

    public mixed $attachment = null;

    public string $publish_at = '';

    public string $expires_at = '';

    public bool $is_active = true;

    public bool $requires_acknowledgement = false;

    public string $survey_url = '';

    public ?int $viewing_id = null;

    public string $department_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Announcement::class);
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return Auth::user()->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    /**
     * @return LengthAwarePaginator<int, Announcement>
     */
    #[Computed]
    public function announcements(): LengthAwarePaginator
    {
        return Announcement::query()
            ->with('department')
            ->withCount('acknowledgements')
            ->unless($this->isSuperAdmin(), fn ($query) => $query->where('department_id', Auth::user()->department_id))
            ->latest()
            ->paginate(15);
    }

    /**
     * Who acknowledged the announcement being viewed, newest first.
     * Staff only: students never reach this component.
     *
     * @return Collection<int, AnnouncementAcknowledgement>
     */
    #[Computed]
    public function acknowledgements(): Collection
    {
        if ($this->viewing_id === null) {
            return new Collection;
        }

        $announcement = $this->findAnnouncement($this->viewing_id);
        $this->authorize('update', $announcement);

        return $announcement->acknowledgements()->with('user.student')->latest('acknowledged_at')->get();
    }

    public function showAcknowledgements(int $id): void
    {
        $this->authorize('update', $this->findAnnouncement($id));

        $this->viewing_id = $id;
        unset($this->acknowledgements);

        $this->modal('announcement-acknowledgements')->show();
    }

    public function startCreate(): void
    {
        $this->authorize('create', Announcement::class);

        $this->reset(['editing_id', 'title', 'body', 'image', 'attachment', 'publish_at', 'expires_at', 'is_active', 'requires_acknowledgement', 'survey_url', 'department_id']);
        $this->is_active = true;
        $this->resetValidation();

        $this->modal('announcement-form')->show();
    }

    public function startEdit(int $id): void
    {
        $announcement = $this->findAnnouncement($id);
        $this->authorize('update', $announcement);

        $this->editing_id = $announcement->id;
        $this->title = $announcement->title;
        $this->body = $announcement->body;
        $this->image = null;
        $this->attachment = null;
        $this->publish_at = $announcement->publish_at?->format('Y-m-d\TH:i') ?? '';
        $this->expires_at = $announcement->expires_at?->format('Y-m-d\TH:i') ?? '';
        $this->is_active = $announcement->is_active;
        $this->requires_acknowledgement = $announcement->requires_acknowledgement;
        $this->survey_url = $announcement->survey_url ?? '';
        $this->department_id = (string) $announcement->department_id;
        $this->resetValidation();

        $this->modal('announcement-form')->show();
    }

    public function save(): void
    {
        $announcement = $this->editing_id ? $this->findAnnouncement($this->editing_id) : null;

        $this->authorize($announcement ? 'update' : 'create', $announcement ?? Announcement::class);

        $validated = $this->validate([
            'title' => $this->titleRules(),
            'body' => $this->bodyRules(),
            'image' => $this->imageRules(),
            'attachment' => $this->attachmentRules(),
            'publish_at' => $this->publishAtRules(),
            'expires_at' => $this->expiresAtRules($this->publish_at ?: null),
            'is_active' => ['boolean'],
            'requires_acknowledgement' => ['boolean'],
            'survey_url' => $this->surveyUrlRules(),
            'department_id' => $this->isSuperAdmin()
                ? ['required', Rule::exists(Department::class, 'id')]
                : ['nullable'],
        ], $this->announcementMessages());

        $attributes = [
            'title' => $validated['title'],
            'body' => $validated['body'],
            'publish_at' => $validated['publish_at'] ?: null,
            'expires_at' => $validated['expires_at'] ?: null,
            'is_active' => $validated['is_active'],
            'requires_acknowledgement' => $validated['requires_acknowledgement'],
            'survey_url' => $validated['survey_url'] ?: null,
        ];

        if ($this->isSuperAdmin()) {
            $attributes['department_id'] = $validated['department_id'];
        }

        if ($announcement === null) {
            $announcement = Announcement::create(array_merge($attributes, [
                'department_id' => $attributes['department_id'] ?? Auth::user()->department_id,
                'created_by' => Auth::id(),
            ]));
        } else {
            $announcement->update($attributes);
        }

        if ($this->image) {
            $announcement->update(['image_path' => $this->image->store('announcements/'.$announcement->id, 'local')]);
        }

        if ($this->attachment) {
            $announcement->update([
                'attachment_path' => $this->attachment->store('announcements/'.$announcement->id, 'local'),
                'attachment_original_name' => $this->attachment->getClientOriginalName(),
            ]);
        }

        unset($this->announcements);
        $this->modal('announcement-form')->close();
        Flux::toast(variant: 'success', text: $this->editing_id ? __('Announcement updated.') : __('Announcement created.'));
    }

    public function toggleActive(int $id): void
    {
        $announcement = $this->findAnnouncement($id);
        $this->authorize('update', $announcement);

        $announcement->update(['is_active' => ! $announcement->is_active]);

        unset($this->announcements);
        Flux::toast(variant: 'success', text: $announcement->is_active ? __('Announcement activated.') : __('Announcement deactivated.'));
    }

    /**
     * Loaded without the admin-department scope: an announcement from
     * another department must still resolve here, so the policy check
     * can deny it with a clean 403 instead of findOrFail's 404.
     */
    private function findAnnouncement(int $id): Announcement
    {
        return Announcement::withoutGlobalScope(AdminDepartmentScope::class)->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.admin.announcements.index');
    }
}
