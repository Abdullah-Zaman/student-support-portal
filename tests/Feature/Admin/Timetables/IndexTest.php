<?php

use App\Enums\TimetableVersionStatus;
use App\Enums\UserStatus;
use App\Livewire\Admin\Timetables\Index;
use App\Models\Department;
use App\Models\TimetableVersion;
use App\Notifications\TimetablePublished;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
    Storage::fake('local');
});

function timetableUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent('Timetable SE Fall-26.xlsx', (string) file_get_contents(makeTimetableWorkbook()));
}

test('an admin officer uploads a timetable as a draft version of their own department', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    $component = Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('file', timetableUpload())
        ->call('uploadTimetable')
        ->assertHasNoErrors();

    $version = TimetableVersion::query()->sole();
    $component->assertRedirect(route('admin.timetable.show', ['version' => $version->id]));

    expect($version->department_id)->toBe($department->id)
        ->and($version->version_no)->toBe(1)
        ->and($version->status)->toBe(TimetableVersionStatus::Draft)
        ->and($version->original_name)->toBe('Timetable SE Fall-26.xlsx')
        ->and($version->class_count)->toBe(6)
        ->and($version->unparsed_cells)->toHaveCount(1)
        ->and($version->sectionNames())->toBe(['BSSE-1A', 'BSSE-1B', 'BSSE-3A', 'BSSE-3B', 'BSSE-5A', 'BSAI-7C', 'BSAI-8A', 'BSDS-8A']);

    Storage::disk('local')->assertExists($version->file_path);
});

test('a second upload in the same department gets the next version number', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    TimetableVersion::factory()->create(['department_id' => $department->id, 'version_no' => 4]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('file', timetableUpload())
        ->call('uploadTimetable')
        ->assertHasNoErrors();

    expect(TimetableVersion::query()->max('version_no'))->toBe(5);
});

test('a file that is not an xlsx workbook is rejected', function () {
    $admin = createAdminOfficer();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('file', UploadedFile::fake()->create('timetable.pdf', 50, 'application/pdf'))
        ->call('uploadTimetable')
        ->assertHasErrors(['file']);

    expect(TimetableVersion::query()->count())->toBe(0);
});

test('a super admin must choose the department to upload for', function () {
    Livewire::actingAs(createSuperAdmin())
        ->test(Index::class)
        ->set('file', timetableUpload())
        ->call('uploadTimetable')
        ->assertHasErrors(['department_id' => 'required']);
});

test('a super admin can upload a timetable for any department', function () {
    $department = Department::factory()->create();

    Livewire::actingAs(createSuperAdmin())
        ->test(Index::class)
        ->set('department_id', (string) $department->id)
        ->set('file', timetableUpload())
        ->call('uploadTimetable')
        ->assertHasNoErrors();

    expect(TimetableVersion::query()->sole()->department_id)->toBe($department->id);
});

test('a student cannot open the timetable management page', function () {
    $this->actingAs(createStudentUser())
        ->get(route('admin.timetable.index'))
        ->assertForbidden();
});

test('a student cannot upload a timetable', function () {
    Livewire::actingAs(createStudentUser())
        ->test(Index::class)
        ->assertForbidden();
});

test('an admin officer cannot publish another department\'s timetable', function () {
    $admin = createAdminOfficer();
    $otherVersion = TimetableVersion::factory()->create();

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('publish', $otherVersion->id)
        ->assertForbidden();

    expect($otherVersion->refresh()->status)->toBe(TimetableVersionStatus::Draft);
});

test('an admin officer only lists their own department\'s versions', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    TimetableVersion::factory()->create(['department_id' => $department->id, 'original_name' => 'Ours.xlsx']);
    TimetableVersion::factory()->create(['original_name' => 'Theirs.xlsx']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Ours.xlsx')
        ->assertDontSee('Theirs.xlsx');
});

test('publishing a version archives the previous one and notifies the department\'s active students', function () {
    Notification::fake();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $activeStudent = createStudentUser($department);
    $suspendedStudent = createStudentUser($department);
    $suspendedStudent->update(['status' => UserStatus::Suspended]);
    $otherDepartmentStudent = createStudentUser();
    $previous = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);
    $draft = TimetableVersion::factory()->create(['department_id' => $department->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('publish', $draft->id)
        ->assertHasNoErrors();

    expect($draft->refresh()->status)->toBe(TimetableVersionStatus::Published)
        ->and($draft->published_by)->toBe($admin->id)
        ->and($previous->refresh()->status)->toBe(TimetableVersionStatus::Archived);

    Notification::assertSentTo($activeStudent, TimetablePublished::class, fn (TimetablePublished $notification, array $channels) => $channels === ['database']);
    Notification::assertNotSentTo([$suspendedStudent, $otherDepartmentStudent], TimetablePublished::class);
});

test('restoring an archived version makes it the published one again', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $archived = TimetableVersion::factory()->archived()->create(['department_id' => $department->id]);
    $current = TimetableVersion::factory()->published()->create(['department_id' => $department->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('publish', $archived->id)
        ->assertHasNoErrors();

    expect($archived->refresh()->status)->toBe(TimetableVersionStatus::Published)
        ->and($current->refresh()->status)->toBe(TimetableVersionStatus::Archived);
});
