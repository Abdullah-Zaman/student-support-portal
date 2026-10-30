<?php

use App\Livewire\Admin\Timetables\Explorer;
use App\Models\Department;
use App\Models\Student;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
    $this->department = Department::factory()->create();
    $this->version = TimetableVersion::factory()->published()->create(['department_id' => $this->department->id]);
});

function addClass(TimetableVersion $version, array $attributes, array $sections = ['BSSE-3A']): TimetableClass
{
    return TimetableClass::factory()->forSections($sections)->create([
        'timetable_version_id' => $version->id,
        'ends_at' => '09:20',
        ...$attributes,
    ]);
}

function seedTimetable(TimetableVersion $version): void
{
    addClass($version, ['day' => 1, 'starts_at' => '08:00', 'ends_at' => '09:20', 'room' => 'Lecture Room # 22', 'course' => 'Operating Systems (Theory)', 'teacher' => 'Mr. Ali Khan']);
    addClass($version, ['day' => 1, 'starts_at' => '09:30', 'ends_at' => '10:50', 'room' => 'Lecture Room # 23', 'course' => 'Operating Systems (Lab)', 'teacher' => '  mr.  ali KHAN ']);
    addClass($version, ['day' => 2, 'starts_at' => '08:00', 'ends_at' => '09:20', 'room' => 'Lecture Room # 22', 'course' => 'Databases', 'teacher' => 'Ms. Sara'], ['BSSE-5B']);
    addClass($version, ['day' => 3, 'starts_at' => '09:30', 'ends_at' => '10:50', 'room' => 'Lecture Room # 24', 'course' => 'Databases', 'teacher' => 'Ms. Sara'], ['BSSE-5B']);
}

test('only admin officers of the department and super admins may explore', function (string $who, bool $allowed) {
    $user = match ($who) {
        'admin' => createAdminOfficer($this->department),
        'super' => createSuperAdmin(),
        'other_admin' => createAdminOfficer(),
        'hod' => createHod($this->department),
        'dean' => createDean(),
        'student' => createStudentUser($this->department),
    };

    expect($user->can('explore', [TimetableVersion::class, $this->department->id]))->toBe($allowed);
})->with([
    ['admin', true], ['super', true], ['other_admin', false], ['hod', false], ['dean', false], ['student', false],
]);

test('hod and students cannot mount the explorer', function () {
    Livewire::actingAs(createHod($this->department))->test(Explorer::class)->assertForbidden();
    Livewire::actingAs(createStudentUser($this->department))->test(Explorer::class)->assertForbidden();
});

test('stat tiles count the published timetable', function () {
    seedTimetable($this->version);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->assertSet('stats', ['teachers' => 2, 'rooms' => 3, 'sections' => 2, 'subjects' => 2, 'classes' => 4]);
});

test('teacher search merges messy names and shows workload and free days', function () {
    seedTimetable($this->version);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'teacher')->set('term', '  ALI ')
        ->assertSee('Mr. Ali Khan')
        ->assertSee('Classes per week')
        ->tap(function ($component) {
            $teacher = $component->get('results')['items']->first();
            expect($teacher['total'])->toBe(2)
                ->and($teacher['teachingDays'])->toBe(1)
                ->and($teacher['freeDays'])->toBe([2, 3]);
        });
});

test('subject search lists teachers, days, rooms and sections', function () {
    seedTimetable($this->version);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'subject')->set('term', 'databases')
        ->assertSee('Ms. Sara')->assertSee('BSSE-5B')->assertSee('Lecture Room # 24')->assertSee('Tuesday')->assertSee('Wednesday')
        ->assertDontSee('Mr. Ali Khan');
});

test('room search shows booked and free slots', function () {
    seedTimetable($this->version);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'room')->set('term', '22')
        ->assertSee('Operating Systems (Theory)')
        ->assertSeeHtml('data-test="free-slots"')
        ->tap(fn ($c) => expect($c->get('results')['items']->first()['days'][1]['free'])->toBe(['9:30 AM – 10:50 AM']));
});

test('room and day filters combine', function () {
    seedTimetable($this->version);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'room')->set('term', '22')->set('day', '1')
        ->assertSee('Operating Systems (Theory)')
        ->assertDontSee('Databases')
        ->tap(function ($component) {
            $room = $component->get('results')['items']->first();
            expect(array_keys($room['days']))->toBe([1]);
        });
});

test('section search shows that sections week', function () {
    seedTimetable($this->version);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'section')->set('term', 'bsse-5b')
        ->assertSee('Databases')->assertSee('Tuesday')
        ->assertDontSee('Operating Systems');
});

test('student search shows the timetable of the selected section', function () {
    seedTimetable($this->version);
    $student = createStudentUser($this->department);
    $student->student->update(['section' => 'BSSE-5B']);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'student')->set('term', strtolower($student->student->registration_no))
        ->assertSee($student->name)
        ->assertSee('Databases')
        ->assertDontSee('Operating Systems');
});

test('student search without a section shows a message', function () {
    seedTimetable($this->version);
    $student = createStudentUser($this->department);

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'student')->set('term', $student->student->registration_no)
        ->assertSee($student->name)
        ->assertSeeHtml('data-test="student-no-section"');
});

test('student of another department is reported as not found', function () {
    seedTimetable($this->version);
    $outsider = createStudentUser();

    Livewire::actingAs(createAdminOfficer($this->department))->test(Explorer::class)
        ->set('mode', 'student')->set('term', Student::withoutGlobalScopes()->where('user_id', $outsider->id)->value('registration_no'))
        ->assertSeeHtml('data-test="student-not-found"')
        ->assertDontSee($outsider->name);
});
