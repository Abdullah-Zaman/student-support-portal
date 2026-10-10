<?php

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;

beforeEach(function () {
    createRoles();
});

test('only a super admin may view, create or update users', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $target = createAdminOfficer($department);

    expect($superAdmin->can('viewAny', User::class))->toBeTrue();
    expect($admin->can('viewAny', User::class))->toBeFalse();
    expect($student->can('viewAny', User::class))->toBeFalse();

    expect($superAdmin->can('create', User::class))->toBeTrue();
    expect($admin->can('create', User::class))->toBeFalse();

    expect($superAdmin->can('update', $target))->toBeTrue();
    expect($admin->can('update', $target))->toBeFalse();
});

test('a super admin cannot suspend themselves', function () {
    $superAdmin = createSuperAdmin();

    expect($superAdmin->can('suspend', $superAdmin))->toBeFalse();
});

test('the last active super admin cannot be suspended', function () {
    $lastActiveSuperAdmin = createSuperAdmin();

    // A second account, already suspended, so it doesn't count as "active"
    // and the only remaining active Super Admin is the target above.
    $actingSuperAdmin = createSuperAdmin();
    $actingSuperAdmin->update(['status' => UserStatus::Suspended]);

    expect($actingSuperAdmin->can('suspend', $lastActiveSuperAdmin))->toBeFalse();
});

test('a super admin can suspend another active super admin when more than one remains active', function () {
    $actingSuperAdmin = createSuperAdmin();
    $targetSuperAdmin = createSuperAdmin();

    expect($actingSuperAdmin->can('suspend', $targetSuperAdmin))->toBeTrue();
});

test('a super admin can suspend and reactivate an admin officer', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $admin = createAdminOfficer($department);

    expect($superAdmin->can('suspend', $admin))->toBeTrue();
    expect($superAdmin->can('reactivate', $admin))->toBeTrue();
});
