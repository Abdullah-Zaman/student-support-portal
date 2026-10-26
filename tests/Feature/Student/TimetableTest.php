<?php

use App\Models\Department;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;

beforeEach(function () {
    createRoles();
});

test('a student sees only the classes of their own section from the published timetable', function () {
    $department = Department::factory()->create();
    $student = createTimetableStudent($department, 'BSSE', 7, 'BSSE-7B');
    $published = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);
    TimetableClass::factory()->forSections(['BSSE-7A', 'BSSE-7B'])->create(['timetable_version_id' => $published->id, 'course' => 'Information Security', 'type' => 'lab']);
    TimetableClass::factory()->forSections(['BSSE-7C'])->create(['timetable_version_id' => $published->id, 'course' => 'Digital Marketing']);
    $draft = TimetableVersion::factory()->create(['department_id' => $department->id]);
    TimetableClass::factory()->forSections(['BSSE-7B'])->create(['timetable_version_id' => $draft->id, 'course' => 'Unpublished Draft Course']);
    $otherDepartment = TimetableVersion::factory()->published()->create();
    TimetableClass::factory()->forSections(['BSSE-7B'])->create(['timetable_version_id' => $otherDepartment->id, 'course' => 'Other Department Course']);

    $this->actingAs($student)
        ->get(route('student.timetable'))
        ->assertOk()
        ->assertSee('Information Security')
        ->assertSee('Lab')
        ->assertDontSee('Digital Marketing')
        ->assertDontSee('Unpublished Draft Course')
        ->assertDontSee('Other Department Course');
});

test('a student without a section is asked to pick one', function () {
    $department = Department::factory()->create();
    $student = createTimetableStudent($department, 'BSSE', 7);
    TimetableVersion::factory()->published()->create(['department_id' => $department->id]);

    $this->actingAs($student)
        ->get(route('student.timetable'))
        ->assertOk()
        ->assertSee('Pick your section');
});

test('a student is told when no timetable has been published', function () {
    $student = createTimetableStudent(Department::factory()->create(), 'BSSE', 7, 'BSSE-7B');

    $this->actingAs($student)
        ->get(route('student.timetable'))
        ->assertOk()
        ->assertSee('No timetable published yet');
});

test('an admin officer cannot open the student timetable page', function () {
    $this->actingAs(createAdminOfficer())
        ->get(route('student.timetable'))
        ->assertForbidden();
});
