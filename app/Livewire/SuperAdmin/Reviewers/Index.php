<?php

namespace App\Livewire\SuperAdmin\Reviewers;

use App\Concerns\PasswordValidationRules;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Closure;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * HOD and Dean accounts. An HOD belongs to one department and a Dean to
 * one faculty; each department has at most one active HOD and each
 * faculty at most one active Dean, so every forward has one recipient.
 */
#[Title('HODs & Deans')]
class Index extends Component
{
    use PasswordValidationRules, WithPagination;

    public ?int $editing_id = null;

    public string $role = '';

    public string $name = '';

    public string $email = '';

    public string $department_id = '';

    public string $faculty_id = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function reviewers(): LengthAwarePaginator
    {
        return User::query()
            ->role([RoleName::Hod->value, RoleName::Dean->value])
            ->with(['department', 'faculty', 'roles'])
            ->latest()
            ->paginate(15);
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
     * @return Collection<int, Faculty>
     */
    #[Computed]
    public function faculties(): Collection
    {
        return Faculty::query()->orderBy('name')->get();
    }

    public function startCreate(): void
    {
        $this->authorize('create', User::class);

        $this->reset(['editing_id', 'role', 'name', 'email', 'department_id', 'faculty_id', 'password', 'password_confirmation']);
        $this->role = RoleName::Hod->value;
        $this->resetValidation();

        $this->modal('reviewer-form')->show();
    }

    public function startEdit(int $id): void
    {
        $reviewer = $this->findReviewer($id);
        $this->authorize('update', $reviewer);

        $this->editing_id = $reviewer->id;
        $this->role = $reviewer->hasRole(RoleName::Dean->value) ? RoleName::Dean->value : RoleName::Hod->value;
        $this->name = $reviewer->name;
        $this->email = $reviewer->email;
        $this->department_id = (string) $reviewer->department_id;
        $this->faculty_id = (string) $reviewer->faculty_id;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetValidation();

        $this->modal('reviewer-form')->show();
    }

    public function save(): void
    {
        $reviewer = $this->editing_id ? $this->findReviewer($this->editing_id) : null;

        $this->authorize($reviewer ? 'update' : 'create', $reviewer ?? User::class);

        $emailDomain = config('students.email_domain');
        $isHod = $this->role === RoleName::Hod->value;

        $validated = $this->validate([
            'role' => ['required', Rule::in([RoleName::Hod->value, RoleName::Dean->value])],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($emailDomain) {
                    if (! str_ends_with(strtolower((string) $value), strtolower($emailDomain))) {
                        $fail(__('The email must be a :domain address.', ['domain' => $emailDomain]));
                    }
                },
                Rule::unique(User::class, 'email')->ignore($reviewer?->id),
            ],
            'department_id' => $isHod
                ? ['required', Rule::exists(Department::class, 'id'), $this->onlyActiveReviewerRule(RoleName::Hod, 'department_id', $reviewer)]
                : ['nullable'],
            'faculty_id' => $isHod
                ? ['nullable']
                : ['required', Rule::exists(Faculty::class, 'id'), $this->onlyActiveReviewerRule(RoleName::Dean, 'faculty_id', $reviewer)],
            'password' => $reviewer === null
                ? $this->passwordRules()
                : ['nullable', 'string', Password::default(), 'confirmed'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'department_id' => $isHod ? $validated['department_id'] : null,
            'faculty_id' => $isHod ? null : $validated['faculty_id'],
        ];

        if ($reviewer === null) {
            $reviewer = User::create(array_merge($attributes, [
                'password' => $validated['password'],
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]));
        } else {
            $reviewer->update($attributes);

            if (! empty($validated['password'])) {
                $reviewer->update(['password' => $validated['password']]);
            }
        }

        $reviewer->syncRoles([$validated['role']]);

        unset($this->reviewers);
        $this->modal('reviewer-form')->close();
        Flux::toast(variant: 'success', text: $this->editing_id ? __('Account updated.') : __('Account created.'));
    }

    public function suspend(int $id): void
    {
        $reviewer = $this->findReviewer($id);
        $this->authorize('suspend', $reviewer);

        $reviewer->update(['status' => UserStatus::Suspended]);

        unset($this->reviewers);
        Flux::toast(variant: 'success', text: __('Account suspended.'));
    }

    public function reactivate(int $id): void
    {
        $reviewer = $this->findReviewer($id);
        $this->authorize('reactivate', $reviewer);

        $isHod = $reviewer->hasRole(RoleName::Hod->value);
        $takenBy = $isHod
            ? User::activeHodForDepartment((int) $reviewer->department_id)
            : User::activeDeanForFaculty($reviewer->faculty_id);

        if ($takenBy !== null) {
            Flux::toast(variant: 'danger', text: $isHod
                ? __(':name is already the active HOD of this department. Suspend them first.', ['name' => $takenBy->name])
                : __(':name is already the active Dean of this faculty. Suspend them first.', ['name' => $takenBy->name]));

            return;
        }

        $reviewer->update(['status' => UserStatus::Active]);

        unset($this->reviewers);
        Flux::toast(variant: 'success', text: __('Account reactivated.'));
    }

    /**
     * Fails when another active HOD (or Dean) already holds this
     * department (or faculty).
     */
    private function onlyActiveReviewerRule(RoleName $role, string $column, ?User $reviewer): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($role, $column, $reviewer) {
            $taken = User::query()
                ->role($role->value)
                ->where($column, $value)
                ->where('status', UserStatus::Active)
                ->when($reviewer !== null, fn ($query) => $query->whereKeyNot($reviewer->id))
                ->exists();

            if ($taken) {
                $fail($role === RoleName::Hod
                    ? __('This department already has an active HOD.')
                    : __('This faculty already has an active Dean.'));
            }
        };
    }

    private function findReviewer(int $id): User
    {
        return User::query()->role([RoleName::Hod->value, RoleName::Dean->value])->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.super-admin.reviewers.index');
    }
}
