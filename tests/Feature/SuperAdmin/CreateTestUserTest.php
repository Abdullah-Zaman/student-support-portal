<?php

use App\Actions\Testing\CreateTestUser;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Livewire\Admin\Students\Index as StudentsIndex;
use App\Livewire\SuperAdmin\Users\CreateTest;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    createRoles();
    config(['portal.allow_test_user_creation' => true]);
});

/**
 * @return array{faculty: Faculty, department: Department, program: Program}
 */
function testUserStructure(): array
{
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $program = Program::factory()->create(['code' => 'BSSE', 'department_id' => $department->id]);

    return compact('faculty', 'department', 'program');
}

/**
 * @param  array<string, string>  $overrides
 * @return array<string, string>
 */
function studentForm(array $overrides = []): array
{
    return [
        'role' => 'student',
        'name' => 'Test Student',
        'email' => 'test.student@gmail.com',
        'password' => 'secret-pass-1',
        'registration_no' => 'test-001',
        'program' => 'BSSE',
        'current_semester' => '5',
        'batch' => 'F22',
        ...$overrides,
    ];
}

function fillAndSave(Testable $component, array $form): Testable
{
    foreach ($form as $key => $value) {
        $component->set($key, $value);
    }

    return $component->call('save');
}

test('the Super Admin creates a verified, active test student that sends nothing and is logged', function () {
    Mail::fake();
    Notification::fake();
    Event::fake([Registered::class, Verified::class]);
    testUserStructure();
    $superAdmin = createSuperAdmin();

    fillAndSave(Livewire::actingAs($superAdmin)->test(CreateTest::class), studentForm())->assertHasNoErrors();

    $user = User::query()->where('email', 'test.student@gmail.com')->firstOrFail();
    expect($user->is_test)->toBeTrue()
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole(RoleName::Student->value))->toBeTrue()
        ->and(Hash::check('secret-pass-1', $user->password))->toBeTrue()
        ->and($user->password)->not->toBe('secret-pass-1')
        ->and($user->student->registration_no)->toBe('TEST-001')
        ->and($user->student->current_semester)->toBe(5)
        ->and($user->student->batch)->toBe('F22')
        ->and($user->department_id)->toBe($user->student->program->department_id);

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Notification::assertNothingSent();
    Event::assertNotDispatched(Registered::class);
    Event::assertNotDispatched(Verified::class);

    $log = Activity::query()->where('description', 'test user created')->where('subject_id', $user->id)->sole();
    expect($log->causer_id)->toBe($superAdmin->id)
        ->and($log->properties['role'])->toBe('student');
});

test('test staff accounts get their role, department or faculty and no student record', function (string $role) {
    $structure = testUserStructure();
    $form = [
        'role' => $role,
        'name' => 'Test Staff',
        'email' => "test.{$role}@example.org",
        'password' => 'secret-pass-1',
        'department_id' => (string) $structure['department']->id,
        'faculty_id' => (string) $structure['faculty']->id,
    ];

    fillAndSave(Livewire::actingAs(createSuperAdmin())->test(CreateTest::class), $form)->assertHasNoErrors();

    $user = User::query()->where('email', $form['email'])->firstOrFail();
    expect($user->hasRole($role))->toBeTrue()
        ->and($user->is_test)->toBeTrue()
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->student)->toBeNull()
        ->and($user->department_id)->toBe($role === 'dean' ? null : $structure['department']->id)
        ->and($user->faculty_id)->toBe($role === 'dean' ? $structure['faculty']->id : null);
})->with(['admin_officer', 'hod', 'dean']);

test('a test student appears like a registered student, with a Test badge, and can be filtered', function () {
    testUserStructure();
    fillAndSave(Livewire::actingAs(createSuperAdmin())->test(CreateTest::class), studentForm())->assertHasNoErrors();
    $department = User::query()->where('email', 'test.student@gmail.com')->firstOrFail()->department;
    $admin = createAdminOfficer($department);
    $realStudent = createStudentUser($department);

    foreach ([createSuperAdmin(), $admin] as $viewer) {
        Livewire::actingAs($viewer)->test(StudentsIndex::class)
            ->assertSee('Test Student')
            ->assertSeeHtml('data-test="test-badge"')
            ->assertSee('TEST-001');
    }

    Livewire::actingAs($admin)->test(StudentsIndex::class)
        ->set('test', 'only')->assertSee('Test Student')->assertDontSee($realStudent->name)
        ->set('test', 'hide')->assertDontSee('Test Student')->assertSee($realStudent->name);
});

test('the test student can log in as themselves only and cannot reach the Super Admin tool', function () {
    testUserStructure();
    fillAndSave(Livewire::actingAs(createSuperAdmin())->test(CreateTest::class), studentForm())->assertHasNoErrors();
    auth()->logout();
    $testStudent = User::query()->where('email', 'test.student@gmail.com')->firstOrFail();

    $this->post('/login', ['email' => 'test.student@gmail.com', 'password' => 'secret-pass-1'])->assertRedirect();
    $this->assertAuthenticatedAs($testStudent);

    $this->get(route('student.dashboard'))->assertOk();
    $this->get(route('super-admin.test-users.create'))->assertForbidden();
    $this->get(route('super-admin.students.index'))->assertForbidden();
});

test('the tool is forbidden and hidden when the flag is off', function () {
    config(['portal.allow_test_user_creation' => false]);
    $superAdmin = createSuperAdmin();

    expect($superAdmin->can('createTest', User::class))->toBeFalse();

    $this->actingAs($superAdmin)->get(route('super-admin.test-users.create'))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('super-admin.dashboard'))->assertDontSee('Create test user');
    Livewire::actingAs($superAdmin)->test(CreateTest::class)->assertForbidden();
    expect(fn () => app(CreateTestUser::class)->handle($superAdmin, studentForm()))->toThrow(HttpException::class);
});

test('the tool is forbidden in production even when the flag is on', function () {
    app()->instance('env', 'production');
    $superAdmin = createSuperAdmin();

    expect($superAdmin->can('createTest', User::class))->toBeFalse();

    Livewire::actingAs($superAdmin)->test(CreateTest::class)->assertForbidden();
    expect(fn () => app(CreateTestUser::class)->handle($superAdmin, studentForm()))->toThrow(HttpException::class)
        ->and(User::query()->where('is_test', true)->count())->toBe(0);
});

test('the link shows for the Super Admin when the tool is on', function () {
    $this->actingAs(createSuperAdmin())->get(route('super-admin.dashboard'))->assertSee('Create test user');
});

test('nobody but the Super Admin can use the tool', function (string $who) {
    $department = Department::factory()->create();
    $user = match ($who) {
        'admin officer' => createAdminOfficer($department),
        'hod' => createHod($department),
        'dean' => createDean(),
        'student' => createStudentUser($department),
    };

    expect($user->can('createTest', User::class))->toBeFalse();

    $this->actingAs($user)->get(route('super-admin.test-users.create'))->assertForbidden();
    Livewire::actingAs($user)->test(CreateTest::class)->assertForbidden();
    expect(User::query()->where('is_test', true)->count())->toBe(0);
})->with(['admin officer', 'hod', 'dean', 'student']);

test('every field is validated and email and registration number must be unique', function () {
    $structure = testUserStructure();
    $existing = createStudentUser($structure['department']);
    $component = Livewire::actingAs(createSuperAdmin())->test(CreateTest::class);

    fillAndSave($component, studentForm(['name' => '', 'email' => 'not-an-email', 'password' => 'short', 'registration_no' => 'bad reg!', 'program' => 'NOPE', 'current_semester' => '9', 'batch' => 'X1']))
        ->assertHasErrors(['name' => 'required', 'email' => 'email', 'password', 'registration_no' => 'regex', 'program' => 'exists', 'current_semester' => 'between', 'batch' => 'regex']);

    fillAndSave($component, studentForm(['email' => $existing->email, 'registration_no' => strtolower($existing->student->registration_no)]))
        ->assertHasErrors(['email' => 'unique', 'registration_no' => 'unique']);

    fillAndSave($component, studentForm(['role' => 'wizard']))->assertHasErrors(['role']);
    fillAndSave($component, ['role' => 'hod', 'email' => 'hod.test@example.org', 'department_id' => ''])->assertHasErrors(['department_id' => 'required']);
    fillAndSave($component, ['role' => 'dean', 'email' => 'dean.test@example.org', 'faculty_id' => ''])->assertHasErrors(['faculty_id' => 'required']);

    expect(User::query()->where('is_test', true)->count())->toBe(0)
        ->and(Student::query()->count())->toBe(1);
});

test('a second active HOD or Dean for the same department or faculty is rejected', function () {
    $structure = testUserStructure();
    createHod($structure['department']);
    createDean($structure['faculty']);
    $base = ['name' => 'Another', 'password' => 'secret-pass-1', 'department_id' => (string) $structure['department']->id, 'faculty_id' => (string) $structure['faculty']->id];
    $component = Livewire::actingAs(createSuperAdmin())->test(CreateTest::class);

    fillAndSave($component, [...$base, 'role' => 'hod', 'email' => 'second.hod@example.org'])->assertHasErrors(['department_id']);
    fillAndSave($component, [...$base, 'role' => 'dean', 'email' => 'second.dean@example.org'])->assertHasErrors(['faculty_id']);

    expect(User::query()->where('is_test', true)->count())->toBe(0);
});
