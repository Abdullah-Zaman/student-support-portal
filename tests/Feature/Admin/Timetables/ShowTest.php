<?php

use App\Livewire\Admin\Timetables\Show;
use App\Models\Department;
use App\Models\TimetableClass;
use App\Models\TimetableVersion;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('the preview shows the class counts, unparsed cells and classes filtered by section', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $version = TimetableVersion::factory()->create([
        'department_id' => $department->id,
        'class_count' => 2,
        'unparsed_cells' => [['sheet' => 'Time Table', 'cell' => 'F5', 'text' => 'Lab closed for maintenance', 'reason' => 'No section (like BSSE-3A) found.']],
    ]);
    TimetableClass::factory()->forSections(['BSSE-3A'])->create(['timetable_version_id' => $version->id, 'course' => 'Software Construction']);
    TimetableClass::factory()->forSections(['BSSE-5A'])->create(['timetable_version_id' => $version->id, 'course' => 'Compiler Construction']);

    Livewire::actingAs($admin)
        ->test(Show::class, ['version' => $version->id])
        ->assertSee('Lab closed for maintenance')
        ->assertSee('Software Construction')
        ->assertSee('Compiler Construction')
        ->set('section', 'BSSE-3A')
        ->assertSee('Software Construction')
        ->assertDontSee('Compiler Construction');
});

test('an admin officer cannot preview another department\'s timetable', function () {
    $admin = createAdminOfficer();
    $otherVersion = TimetableVersion::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.timetable.show', ['version' => $otherVersion->id]))
        ->assertForbidden();
});
