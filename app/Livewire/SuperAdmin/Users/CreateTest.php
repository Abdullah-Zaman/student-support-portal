<?php

namespace App\Livewire\SuperAdmin\Users;

use App\Actions\Testing\CreateTestUser;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Super Admin tool for testing only: creates verified, active accounts
 * (students, or Admin Officer / HOD / Dean test staff) with any email
 * domain and sends nothing. Exists only while the test-user flag is on
 * and the app is not in production (see TestUserCreation); the policy
 * enforces that on mount and on every save.
 *
 * @property-read Collection<int, Program> $programs
 */
#[Title('Create test user')]
class CreateTest extends Component
{
    public string $role = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $registration_no = '';

    public string $program = '';

    public string $current_semester = '7';

    public string $batch = 'F22';

    public string $department_id = '';

    public string $faculty_id = '';

    public function mount(): void
    {
        $this->authorize('createTest', User::class);

        $this->role = RoleName::Student->value;
        $this->program = (string) $this->programs->first()?->code;
        $this->registration_no = 'SU92-'.($this->program !== '' ? $this->program : 'BSSE').'M-'.$this->batch.'-901';
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function roles(): array
    {
        return [
            RoleName::Student->value => __('Student'),
            RoleName::AdminOfficer->value => __('Admin Officer'),
            RoleName::Hod->value => __('HOD'),
            RoleName::Dean->value => __('Dean'),
        ];
    }

    /**
     * @return Collection<int, Program>
     */
    #[Computed]
    public function programs(): Collection
    {
        return Program::withoutGlobalScopes()->orderBy('code')->get();
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

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function recentTestUsers(): Collection
    {
        return User::query()->where('is_test', true)->with('roles')->latest('id')->take(10)->get();
    }

    public function save(CreateTestUser $createTestUser): void
    {
        $this->authorize('createTest', User::class);

        $this->registration_no = strtoupper(trim($this->registration_no));
        $isStudent = $this->role === RoleName::Student->value;
        $isDean = $this->role === RoleName::Dean->value;

        $validated = $this->validate([
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', Password::min(8)],
            'registration_no' => $isStudent
                ? ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique(Student::class, 'registration_no')]
                : ['nullable'],
            'program' => $isStudent ? ['required', Rule::exists(Program::class, 'code')] : ['nullable'],
            'current_semester' => $isStudent ? ['required', 'integer', 'between:1,8'] : ['nullable'],
            'batch' => $isStudent ? ['required', 'regex:/^[FS]\d{2}$/'] : ['nullable'],
            'department_id' => in_array($this->role, [RoleName::AdminOfficer->value, RoleName::Hod->value], true)
                ? ['required', Rule::exists(Department::class, 'id')]
                : ['nullable'],
            'faculty_id' => $isDean ? ['required', Rule::exists(Faculty::class, 'id')] : ['nullable'],
        ], [
            'batch.regex' => __('Use a batch like F22 or S23.'),
            'registration_no.regex' => __('Use letters, numbers and dashes only.'),
        ]);

        $this->guardSingleReviewer($validated);

        $user = $createTestUser->handle(auth()->user(), $validated);

        $this->reset('name', 'email', 'password');
        $this->registration_no = 'SU92-'.($this->program !== '' ? $this->program : 'BSSE').'M-'.$this->batch.'-'.str_pad((string) (((int) substr($this->registration_no, -3)) + 1), 3, '0', STR_PAD_LEFT);
        unset($this->recentTestUsers);

        Flux::toast(variant: 'success', text: __('Test user :email created. Nothing was emailed.', ['email' => $user->email]));
    }

    public function render(): View
    {
        return view('livewire.super-admin.users.create-test');
    }

    /**
     * Each department has at most one active HOD and each faculty one
     * active Dean, so every forward has a single recipient.
     *
     * @param  array<string, mixed>  $validated
     *
     * @throws ValidationException
     */
    private function guardSingleReviewer(array $validated): void
    {
        if ($validated['role'] === RoleName::Hod->value && User::activeHodForDepartment((int) $validated['department_id']) !== null) {
            throw ValidationException::withMessages(['department_id' => __('This department already has an active HOD.')]);
        }

        if ($validated['role'] === RoleName::Dean->value && User::activeDeanForFaculty((int) $validated['faculty_id']) !== null) {
            throw ValidationException::withMessages(['faculty_id' => __('This faculty already has an active Dean.')]);
        }
    }
}
