<?php

use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Database\Seeders\ReviewerUserSeeder;

beforeEach(function () {
    createRoles();
});

test('HOD and Dean are forbidden from admin and super admin pages', function (string $role, string $routeName) {
    $user = $role === 'hod' ? createHod() : createDean();

    $this->actingAs($user)->get(route($routeName))->assertForbidden();
})->with(['hod', 'dean'])->with([
    'admin.dashboard',
    'admin.applications.index',
    'admin.students.index',
    'admin.timetable.index',
    'super-admin.dashboard',
    'super-admin.reviewers.index',
    'super-admin.settings.edit',
    'student.dashboard',
]);

test('an HOD cannot use the Dean portal and a Dean cannot use the HOD portal', function () {
    $this->actingAs(createHod())->get(route('dean.dashboard'))->assertForbidden();
    $this->actingAs(createDean())->get(route('hod.dashboard'))->assertForbidden();
});

test('an admin officer cannot open the HOD portal', function () {
    $this->actingAs(createAdminOfficer())->get(route('hod.applications.index'))->assertForbidden();
});

test('HOD and Dean land on their own dashboard after login', function (string $role) {
    $user = $role === 'hod' ? createHod() : createDean();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route($role.'.dashboard'));
    $this->actingAs($user)->get(route($role.'.dashboard'))->assertOk();
})->with(['hod', 'dean']);

test('the seeded HOD and Dean belong to the SE department and the Faculty of Computing', function () {
    seedAcademicStructure();
    $this->seed(ReviewerUserSeeder::class);

    $hod = User::query()->where('email', 'hod.se@superior.edu.pk')->sole();
    $dean = User::query()->where('email', 'dean@superior.edu.pk')->sole();

    expect($hod->hasRole('hod'))->toBeTrue()
        ->and($hod->department_id)->toBe(Department::query()->where('code', 'SE')->value('id'))
        ->and($dean->hasRole('dean'))->toBeTrue()
        ->and($dean->faculty_id)->toBe(Faculty::query()->where('code', 'FOC')->value('id'));
});
