<?php

use App\Models\Department;

beforeEach(function () {
    createRoles();
});

test('an admin officer and a student get a 403 from the super admin dashboard', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);

    $this->actingAs($admin)->get(route('super-admin.dashboard'))->assertForbidden();
    $this->actingAs($student)->get(route('super-admin.dashboard'))->assertForbidden();
});

test('the super admin dashboard shows totals across every department', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    createAdminOfficer($departmentA);
    createAdminOfficer($departmentB);
    createApplicationInDepartment($departmentA, createStudentUser($departmentA));
    createApplicationInDepartment($departmentB, createStudentUser($departmentB));

    $response = $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));

    $response->assertOk();
    $response->assertViewHas('stats', function (array $stats) {
        return $stats['departments'] === 2
            && $stats['admin_officers'] === 2
            && $stats['students'] === 2
            && $stats['applications'] === 2;
    });
});
