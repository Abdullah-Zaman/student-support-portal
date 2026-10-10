<?php

namespace App\Livewire\SuperAdmin\AdminOfficers;

use App\Concerns\PasswordValidationRules;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Department;
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

#[Title('Admin Officers')]
class Index extends Component
{
    use PasswordValidationRules, WithPagination;

    public ?int $editing_id = null;

    public string $name = '';

    public string $email = '';

    public string $department_id = '';

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
    public function admins(): LengthAwarePaginator
    {
        return User::query()
            ->role(RoleName::AdminOfficer->value)
            ->with('department')
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

    public function startCreate(): void
    {
        $this->authorize('create', User::class);

        $this->reset(['editing_id', 'name', 'email', 'department_id', 'password', 'password_confirmation']);
        $this->resetValidation();

        $this->modal('admin-officer-form')->show();
    }

    public function startEdit(int $id): void
    {
        $admin = $this->findAdmin($id);
        $this->authorize('update', $admin);

        $this->editing_id = $admin->id;
        $this->name = $admin->name;
        $this->email = $admin->email;
        $this->department_id = (string) $admin->department_id;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetValidation();

        $this->modal('admin-officer-form')->show();
    }

    public function save(): void
    {
        $admin = $this->editing_id ? $this->findAdmin($this->editing_id) : null;

        $this->authorize($admin ? 'update' : 'create', $admin ?? User::class);

        $emailDomain = config('students.email_domain');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($emailDomain) {
                    if (! str_ends_with(strtolower((string) $value), strtolower($emailDomain))) {
                        $fail(__('The email must be a :domain address.', ['domain' => $emailDomain]));
                    }
                },
                Rule::unique(User::class, 'email')->ignore($admin?->id),
            ],
            'department_id' => ['required', Rule::exists(Department::class, 'id')],
            'password' => $admin === null
                ? $this->passwordRules()
                : ['nullable', 'string', Password::default(), 'confirmed'],
        ]);

        $attributes = [
            'name' => $validated['name'],
            'email' => strtolower($validated['email']),
            'department_id' => $validated['department_id'],
        ];

        if ($admin === null) {
            $admin = User::create(array_merge($attributes, [
                'password' => $validated['password'],
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]));
            $admin->assignRole(RoleName::AdminOfficer->value);
        } else {
            $admin->update($attributes);

            if (! empty($validated['password'])) {
                $admin->update(['password' => $validated['password']]);
            }
        }

        unset($this->admins);
        $this->modal('admin-officer-form')->close();
        Flux::toast(variant: 'success', text: $this->editing_id ? __('Admin Officer updated.') : __('Admin Officer created.'));
    }

    public function suspend(int $id): void
    {
        $admin = $this->findAdmin($id);
        $this->authorize('suspend', $admin);

        $admin->update(['status' => UserStatus::Suspended]);

        unset($this->admins);
        Flux::toast(variant: 'success', text: __('Admin Officer suspended.'));
    }

    public function reactivate(int $id): void
    {
        $admin = $this->findAdmin($id);
        $this->authorize('reactivate', $admin);

        $admin->update(['status' => UserStatus::Active]);

        unset($this->admins);
        Flux::toast(variant: 'success', text: __('Admin Officer reactivated.'));
    }

    private function findAdmin(int $id): User
    {
        return User::query()->role(RoleName::AdminOfficer->value)->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.super-admin.admin-officers.index');
    }
}
