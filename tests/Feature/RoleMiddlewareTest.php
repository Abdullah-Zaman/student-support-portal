<?php

use App\Enums\RoleName;
use App\Models\Department;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    createRoles();
});

test('a guest hitting an admin route is redirected to login and back to it after authenticating', function () {
    $response = $this->get(route('admin.dashboard'));
    $response->assertRedirect(route('login'));

    $user = User::factory()->create();
    $user->assignRole(RoleName::AdminOfficer->value);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('admin.dashboard'));
});

test('an admin officer logging in with a super admin url intended lands on their own dashboard', function () {
    $response = $this->get(route('super-admin.dashboard'));
    $response->assertRedirect(route('login'));

    $user = User::factory()->create();
    $user->assignRole(RoleName::AdminOfficer->value);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));

    $this->get(route('dashboard'))->assertRedirect(route('admin.dashboard'));
});

test('a student cannot access the admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student->value);

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

test('a student cannot access the super admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Student->value);

    $this->actingAs($user)->get(route('super-admin.dashboard'))->assertForbidden();
});

test('an admin officer cannot access the student dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::AdminOfficer->value);

    $this->actingAs($user)->get(route('student.dashboard'))->assertForbidden();
});

test('an admin officer cannot access the super admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::AdminOfficer->value);

    $this->actingAs($user)->get(route('super-admin.dashboard'))->assertForbidden();
});

test('a super admin cannot access the student or admin dashboards', function () {
    $user = User::factory()->create();
    $user->assignRole(RoleName::SuperAdmin->value);

    $this->actingAs($user)->get(route('student.dashboard'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

test('each role can reach its own dashboard', function () {
    $student = createStudentUser();
    $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

    $admin = User::factory()->create();
    $admin->assignRole(RoleName::AdminOfficer->value);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(RoleName::SuperAdmin->value);
    $this->actingAs($superAdmin)->get(route('super-admin.dashboard'))->assertOk();
});

test('an admin officer can reach their own applications and students index', function () {
    $admin = createAdminOfficer();

    $this->actingAs($admin)->get(route('admin.applications.index'))->assertOk();
    $this->actingAs($admin)->get(route('admin.students.index'))->assertOk();
});

test('a student cannot access the admin applications or students index', function () {
    $student = createStudentUser();

    $this->actingAs($student)->get(route('admin.applications.index'))->assertForbidden();
    $this->actingAs($student)->get(route('admin.students.index'))->assertForbidden();
});

test('a super admin cannot access the admin officer applications or students index', function () {
    $superAdmin = createSuperAdmin();

    $this->actingAs($superAdmin)->get(route('admin.applications.index'))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('admin.students.index'))->assertForbidden();
});

test('an admin officer and a student cannot reach any super admin only route', function () {
    $admin = createAdminOfficer();
    $student = createStudentUser();

    $superAdminOnlyRoutes = [
        route('super-admin.admins.index'),
        route('super-admin.categories.index'),
        route('super-admin.audit-log.index'),
        route('super-admin.settings.edit'),
    ];

    foreach ($superAdminOnlyRoutes as $url) {
        $this->actingAs($admin)->get($url)->assertForbidden();
        $this->actingAs($student)->get($url)->assertForbidden();
    }
});

test('an admin officer gets 403 on every super admin page, including ones in their own department', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $parameters = [
        'student' => $student->student->id,
        'application' => createApplicationInDepartment($department, $student)->id,
        'version' => TimetableVersion::factory()->create(['department_id' => $department->id])->id,
    ];

    $superAdminPages = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (IlluminateRoute $route) => str_starts_with((string) $route->getName(), 'super-admin.') && in_array('GET', $route->methods(), true))
        ->map(fn (IlluminateRoute $route) => route($route->getName(), array_intersect_key($parameters, array_flip($route->parameterNames()))));

    expect($superAdminPages)->not->toBeEmpty();

    foreach ($superAdminPages as $url) {
        $this->actingAs($admin)->get($url)->assertForbidden();
    }
});
