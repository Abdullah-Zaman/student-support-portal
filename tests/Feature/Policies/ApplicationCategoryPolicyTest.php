<?php

use App\Models\ApplicationCategory;
use App\Models\Department;

beforeEach(function () {
    createRoles();
});

test('only a super admin may manage categories', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);

    expect($superAdmin->can('viewAny', ApplicationCategory::class))->toBeTrue();
    expect($admin->can('viewAny', ApplicationCategory::class))->toBeFalse();
    expect($student->can('viewAny', ApplicationCategory::class))->toBeFalse();

    expect($superAdmin->can('create', ApplicationCategory::class))->toBeTrue();
    expect($admin->can('create', ApplicationCategory::class))->toBeFalse();

    expect($superAdmin->can('update', $category))->toBeTrue();
    expect($admin->can('update', $category))->toBeFalse();
});

test('categories can never be deleted, by anyone', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);

    expect($superAdmin->can('delete', $category))->toBeFalse();
});
