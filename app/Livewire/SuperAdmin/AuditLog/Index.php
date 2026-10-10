<?php

namespace App\Livewire\SuperAdmin\AuditLog;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Activitylog\Models\Activity;

#[Title('Audit Log')]
class Index extends Component
{
    use WithPagination;

    /**
     * Never surfaced in a diff, however an activity is logged — defensive,
     * even though no model currently exposes these as loggable attributes.
     */
    private const HIDDEN_FIELDS = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    #[Url]
    public string $causer_id = '';

    #[Url]
    public string $subject_type = '';

    #[Url]
    public string $date_from = '';

    #[Url]
    public string $date_to = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Activity::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['causer_id', 'subject_type', 'date_from', 'date_to'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['causer_id', 'subject_type', 'date_from', 'date_to']);
        $this->resetPage();
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->causer_id !== ''
            || $this->subject_type !== ''
            || $this->date_from !== ''
            || $this->date_to !== '';
    }

    /**
     * @return LengthAwarePaginator<int, Activity>
     */
    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::query()
            ->with('causer')
            ->when($this->causer_id !== '', fn (Builder $query) => $query->where('causer_id', $this->causer_id)->where('causer_type', User::class))
            ->when($this->subject_type === '__authentication', fn (Builder $query) => $query->whereNull('subject_type'))
            ->when($this->subject_type !== '' && $this->subject_type !== '__authentication', fn (Builder $query) => $query->where('subject_type', $this->subject_type))
            ->when($this->date_from !== '', fn (Builder $query) => $query->whereDate('created_at', '>=', $this->date_from))
            ->when($this->date_to !== '', fn (Builder $query) => $query->whereDate('created_at', '<=', $this->date_to))
            ->latest('id')
            ->paginate(20);
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function causers(): Collection
    {
        return User::query()->orderBy('name')->get();
    }

    /**
     * Every distinct subject_type logged so far, labelled, plus a pseudo
     * type for causer-only rows (logins/failed logins have no subject).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function subjectTypes(): array
    {
        $types = Activity::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->mapWithKeys(fn (string $type) => [$type => Str::headline(class_basename($type))])
            ->all();

        return ['__authentication' => __('Authentication (login)'), ...$types];
    }

    /**
     * The diff for a single activity, with any sensitive field stripped
     * even though no currently loggable model exposes one.
     *
     * @return array{old: array<string, mixed>, attributes: array<string, mixed>}
     */
    public function diffFor(Activity $activity): array
    {
        $old = (array) $activity->attribute_changes?->get('old', []);
        $attributes = (array) $activity->attribute_changes?->get('attributes', []);

        foreach (self::HIDDEN_FIELDS as $field) {
            unset($old[$field], $attributes[$field]);
        }

        return ['old' => $old, 'attributes' => $attributes];
    }

    public function render(): View
    {
        return view('livewire.super-admin.audit-log.index');
    }
}
