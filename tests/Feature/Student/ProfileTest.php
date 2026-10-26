<?php

use App\Livewire\Student\Profile;
use App\Models\Department;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('a student can view their profile page', function () {
    $student = createStudentUser();

    $response = $this->actingAs($student)->get(route('student.profile'));

    $response->assertOk()
        ->assertSee($student->student->registration_no)
        ->assertSee($student->email);
});

test('a student can update their current semester', function () {
    $student = createStudentUser();
    $student->student->update(['current_semester' => 3]);

    Livewire::actingAs($student)
        ->test(Profile::class)
        ->set('current_semester', 7)
        ->call('updateSemester')
        ->assertHasNoErrors();

    expect($student->student->refresh()->current_semester)->toBe(7);
});

test('the current semester must be between 1 and 8', function () {
    $student = createStudentUser();

    Livewire::actingAs($student)
        ->test(Profile::class)
        ->set('current_semester', 9)
        ->call('updateSemester')
        ->assertHasErrors(['current_semester']);
});

test('batch is displayed as a readable label', function () {
    $student = createStudentUser();
    $student->student->update(['batch' => 'F22']);

    expect($student->student->batch_label)->toBe('Fall 2022');

    $student->student->update(['batch' => 'S24']);

    expect($student->student->refresh()->batch_label)->toBe('Spring 2024');
});

test('the section dropdown lists the student\'s current-semester sections, and all program sections on request', function () {
    $department = Department::factory()->create();
    $student = createTimetableStudent($department, 'BSSE', 7);
    $version = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);
    TimetableClass::factory()->forSections(['BSSE-7A', 'BSSE-7B'])->create(['timetable_version_id' => $version->id]);
    TimetableClass::factory()->forSections(['BSSE-5A', 'BSAI-7A'])->create(['timetable_version_id' => $version->id]);

    $component = Livewire::actingAs($student)->test(Profile::class);

    expect($component->get('sectionOptions'))->toBe(['BSSE-7A', 'BSSE-7B']);

    $component->set('showAllSections', true);

    expect($component->get('sectionOptions'))->toBe(['BSSE-5A', 'BSSE-7A', 'BSSE-7B']);
});

test('a student can save a section of their program from another semester', function () {
    $department = Department::factory()->create();
    $student = createTimetableStudent($department, 'BSSE', 7);
    $version = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);
    TimetableClass::factory()->forSections(['BSSE-5A'])->create(['timetable_version_id' => $version->id]);

    Livewire::actingAs($student)
        ->test(Profile::class)
        ->set('section', 'BSSE-5A')
        ->call('updateSection')
        ->assertHasNoErrors();

    expect($student->student->refresh()->section)->toBe('BSSE-5A');
});

test('a student cannot save a section that is not in their program\'s published timetable', function (string $section) {
    $department = Department::factory()->create();
    $student = createTimetableStudent($department, 'BSSE', 7);
    $version = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);
    TimetableClass::factory()->forSections(['BSSE-7A', 'BSAI-7A'])->create(['timetable_version_id' => $version->id]);

    Livewire::actingAs($student)
        ->test(Profile::class)
        ->set('section', $section)
        ->call('updateSection')
        ->assertHasErrors(['section' => 'in']);

    expect($student->student->refresh()->section)->toBeNull();
})->with(['another program' => 'BSAI-7A', 'not in the timetable' => 'BSSE-7Z']);

test('a student who saved a section from another semester sees all program sections when the profile opens', function () {
    $department = Department::factory()->create();
    $student = createTimetableStudent($department, 'BSSE', 7, 'BSSE-5A');
    $version = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);
    TimetableClass::factory()->forSections(['BSSE-5A', 'BSSE-7A'])->create(['timetable_version_id' => $version->id]);

    Livewire::actingAs($student)
        ->test(Profile::class)
        ->assertSet('section', 'BSSE-5A')
        ->assertSet('showAllSections', true);
});
