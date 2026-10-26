<?php

use App\Models\Department;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    createRoles();
});

/**
 * A BSSE-5A student whose published timetable has two Monday classes
 * (09:20–10:40 and 13:30–14:50) and one Tuesday class.
 */
function studentWithMondayClasses(): User
{
    $department = Department::factory()->create();
    $student = createTimetableStudent($department, 'BSSE', 5, 'BSSE-5A');
    $version = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);

    TimetableClass::factory()->forSections(['BSSE-5A'])->create([
        'timetable_version_id' => $version->id, 'day' => 1, 'starts_at' => '09:20', 'ends_at' => '10:40',
        'course' => 'Operating Systems (Lab)', 'room' => 'Computer Lab-9', 'teacher' => 'Mr. Muhammad Abdullah',
    ]);
    TimetableClass::factory()->forSections(['BSSE-5A'])->create([
        'timetable_version_id' => $version->id, 'day' => 1, 'starts_at' => '13:30', 'ends_at' => '14:50',
        'course' => 'Web Development & Design', 'room' => 'Lecture Room # 22', 'teacher' => 'Mr. Shahid',
    ]);
    TimetableClass::factory()->forSections(['BSSE-5A'])->create([
        'timetable_version_id' => $version->id, 'day' => 2, 'starts_at' => '08:00', 'ends_at' => '09:20',
        'course' => 'Technical & Business Writing',
    ]);

    return $student;
}

test('the app runs on Pakistan time', function () {
    expect(config('app.timezone'))->toBe('Asia/Karachi');
});

test('the today card shows the class happening now and the next one in Pakistan time', function () {
    $student = studentWithMondayClasses();

    // 05:00 UTC is 10:00 in Lahore, on Monday 26 October 2026.
    $this->travelTo(Carbon::parse('2026-10-26 05:00:00', 'UTC'));

    $this->actingAs($student)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['Now', '9:20 AM – 10:40 AM', 'Operating Systems (Lab)', 'Computer Lab-9', 'Mr. Muhammad Abdullah', 'Ends in', '40m'])
        ->assertSeeInOrder(['Next', '1:30 PM – 2:50 PM', 'Web Development &amp; Design', 'Starts in', '3h 30m'], false)
        ->assertDontSee('Technical &amp; Business Writing', false);
});

test('the today card counts down to the next class between classes', function () {
    $student = studentWithMondayClasses();

    $this->travelTo(Carbon::parse('2026-10-26 12:45:00', 'Asia/Karachi'));

    $this->actingAs($student)
        ->get(route('student.dashboard'))
        ->assertOk()
        ->assertDontSee('data-test="today-now"', false)
        ->assertSeeInOrder(['Next', 'Web Development &amp; Design', 'Starts in', '45m'], false);
});

test('the today card says when the day\'s classes are over', function () {
    $student = studentWithMondayClasses();

    $this->travelTo(Carbon::parse('2026-10-26 15:00:00', 'Asia/Karachi'));

    $this->actingAs($student)
        ->get(route('student.dashboard'))
        ->assertSee("You're done for today.");
});

test('the today card says there are no classes on a free day', function () {
    $student = studentWithMondayClasses();

    $this->travelTo(Carbon::parse('2026-10-28 09:00:00', 'Asia/Karachi'));

    $this->actingAs($student)
        ->get(route('student.dashboard'))
        ->assertSee('No classes today.');
});
