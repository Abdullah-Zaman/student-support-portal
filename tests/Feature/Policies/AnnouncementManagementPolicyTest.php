<?php

use App\Models\Announcement;
use App\Models\Department;

beforeEach(function () {
    createRoles();
});

test('admin officer, hod and super admin can create announcements but a student cannot', function () {
    $department = Department::factory()->create();

    expect(createAdminOfficer($department)->can('create', Announcement::class))->toBeTrue();
    expect(createHod($department)->can('create', Announcement::class))->toBeTrue();
    expect(createSuperAdmin()->can('create', Announcement::class))->toBeTrue();
    expect(createStudentUser($department)->can('create', Announcement::class))->toBeFalse();
});

test('a hod manages only announcements of their own department', function () {
    $own = Department::factory()->create();
    $other = Department::factory()->create();
    $hod = createHod($own);

    expect($hod->can('update', Announcement::factory()->create(['department_id' => $own->id])))->toBeTrue();
    expect($hod->can('update', Announcement::factory()->create(['department_id' => $other->id])))->toBeFalse();
    expect(createSuperAdmin()->can('update', Announcement::factory()->create(['department_id' => $other->id])))->toBeTrue();
});

test('a student cannot update an announcement', function () {
    $department = Department::factory()->create();
    $announcement = Announcement::factory()->create(['department_id' => $department->id]);

    expect(createStudentUser($department)->can('update', $announcement))->toBeFalse();
});
