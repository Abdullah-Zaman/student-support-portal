<?php

use App\Models\Department;
use App\Models\Office;

beforeEach(function () {
    createRoles();
});

test('only the Admin Officer of the same department manages an office', function () {
    $department = Department::factory()->create();
    $office = Office::factory()->create(['department_id' => $department->id]);

    $admin = createAdminOfficer($department);

    expect($admin->can('viewAny', Office::class))->toBeTrue()
        ->and($admin->can('create', Office::class))->toBeTrue()
        ->and($admin->can('update', $office))->toBeTrue()
        ->and($admin->can('delete', $office))->toBeFalse();
});

test('everyone else is denied from updating an office', function (string $who) {
    $department = Department::factory()->create();
    $office = Office::factory()->create(['department_id' => $department->id]);

    $user = match ($who) {
        'super admin' => createSuperAdmin(),
        'HOD' => createHod($department),
        'Dean' => createDean($department->faculty),
        'student' => createStudentUser($department),
        'other department admin' => createAdminOfficer(Department::factory()->create()),
    };

    expect($user->can('update', $office))->toBeFalse();

    if ($who !== 'other department admin') {
        expect($user->can('viewAny', Office::class))->toBeFalse()
            ->and($user->can('create', Office::class))->toBeFalse();
    }
})->with(['super admin', 'HOD', 'Dean', 'student', 'other department admin']);
