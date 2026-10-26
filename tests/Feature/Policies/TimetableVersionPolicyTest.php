<?php

use App\Models\Department;
use App\Models\TimetableVersion;

beforeEach(function () {
    createRoles();
});

test('the department\'s admin officer can view, publish and download its timetable versions', function (string $ability) {
    $department = Department::factory()->create();
    $version = TimetableVersion::factory()->create(['department_id' => $department->id]);

    expect(createAdminOfficer($department)->can($ability, $version))->toBeTrue();
})->with(['view', 'publish', 'download']);

test('another department\'s admin officer cannot view, publish or download a timetable version', function (string $ability) {
    $version = TimetableVersion::factory()->create();

    expect(createAdminOfficer()->can($ability, $version))->toBeFalse();
})->with(['view', 'publish', 'download']);

test('a super admin can view, publish and download any timetable version', function (string $ability) {
    $version = TimetableVersion::factory()->create();

    expect(createSuperAdmin()->can($ability, $version))->toBeTrue();
})->with(['view', 'publish', 'download']);

test('a student cannot manage timetable versions, even of their own department', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $version = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);

    expect($student->can('viewAny', TimetableVersion::class))->toBeFalse()
        ->and($student->can('create', TimetableVersion::class))->toBeFalse()
        ->and($student->can('view', $version))->toBeFalse()
        ->and($student->can('publish', $version))->toBeFalse();
});

test('nobody can delete a timetable version', function () {
    $version = TimetableVersion::factory()->create();

    expect(createSuperAdmin()->can('delete', $version))->toBeFalse();
});
