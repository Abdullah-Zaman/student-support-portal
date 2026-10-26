<?php

use App\Models\Department;
use App\Models\TimetableVersion;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    createRoles();
    Storage::fake('local');
});

test('an admin officer downloads their department\'s original timetable file under its original name', function () {
    $department = Department::factory()->create();
    $version = TimetableVersion::factory()->create([
        'department_id' => $department->id,
        'file_path' => 'timetables/1/random-name.xlsx',
        'original_name' => 'Timetable SE Fall-26.xlsx',
    ]);
    Storage::disk('local')->put($version->file_path, 'workbook bytes');

    $this->actingAs(createAdminOfficer($department))
        ->get(route('timetable.download', $version->id))
        ->assertOk()
        ->assertDownload('Timetable SE Fall-26.xlsx');
});

test('an admin officer cannot download another department\'s timetable file', function () {
    $version = TimetableVersion::factory()->create(['file_path' => 'timetables/2/random-name.xlsx']);
    Storage::disk('local')->put($version->file_path, 'workbook bytes');

    $this->actingAs(createAdminOfficer())
        ->get(route('timetable.download', $version->id))
        ->assertForbidden();
});

test('a student cannot download a timetable file', function () {
    $department = Department::factory()->create();
    $version = TimetableVersion::factory()->published()->create(['department_id' => $department->id, 'file_path' => 'timetables/3/random-name.xlsx']);
    Storage::disk('local')->put($version->file_path, 'workbook bytes');

    $this->actingAs(createStudentUser($department))
        ->get(route('timetable.download', $version->id))
        ->assertForbidden();
});
