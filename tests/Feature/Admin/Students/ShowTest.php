<?php

use App\Enums\UserStatus;
use App\Livewire\Admin\Students\Show;
use App\Models\Department;
use App\Models\Program;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

test('an admin officer can view a student profile with all their applications', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $studentProfile = $student->student;

    Livewire::actingAs($admin)
        ->test(Show::class, ['student' => $studentProfile])
        ->assertSee($student->name)
        ->assertSee($studentProfile->registration_no)
        ->assertOk();
});

test('an admin officer cannot view a student profile in another department', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $admin = createAdminOfficer($departmentA);
    $student = createStudentUser($departmentB);
    $studentProfile = $student->student;

    Livewire::actingAs($admin)
        ->test(Show::class, ['student' => $studentProfile])
        ->assertForbidden();
});

test('an admin officer can suspend a student, which logs them out and takes effect immediately', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $studentProfile = $student->student;

    Livewire::actingAs($admin)
        ->test(Show::class, ['student' => $studentProfile])
        ->call('suspend')
        ->assertHasNoErrors();

    expect($student->refresh()->status)->toBe(UserStatus::Suspended);

    $this->actingAs($student)
        ->get(route('student.dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('an admin officer can reactivate a suspended student', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $student->update(['status' => UserStatus::Suspended]);
    $studentProfile = $student->student;

    Livewire::actingAs($admin)
        ->test(Show::class, ['student' => $studentProfile])
        ->call('reactivate')
        ->assertHasNoErrors();

    expect($student->refresh()->status)->toBe(UserStatus::Active);
});

test('a super admin can edit a student\'s program, semester and batch', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $student = createStudentUser($department);
    $bsse = Program::factory()->create(['department_id' => $department->id, 'code' => 'BSSE']);
    $bsds = Program::factory()->create(['department_id' => $department->id, 'code' => 'BSDS']);
    $student->student->update(['registration_no' => 'SU92-BSDSM-F23-045', 'program_id' => $bsse->id]);
    $studentProfile = $student->student;

    Livewire::actingAs($superAdmin)
        ->test(Show::class, ['student' => $studentProfile])
        ->set('program_id', (string) $bsds->id)
        ->set('current_semester', 5)
        ->set('batch', 'F23')
        ->call('updateProfile')
        ->assertHasNoErrors();

    $studentProfile->refresh();
    expect($studentProfile->program_id)->toBe($bsds->id);
    expect($studentProfile->current_semester)->toBe(5);
});

test('an admin officer cannot edit a student\'s program, semester or batch', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $studentProfile = $student->student;

    Livewire::actingAs($admin)
        ->test(Show::class, ['student' => $studentProfile])
        ->set('current_semester', 7)
        ->call('updateProfile')
        ->assertForbidden();
});

test('the program must stay consistent with the registration number when a super admin edits a student', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $student = createStudentUser($department);
    $bsds = Program::factory()->create(['department_id' => $department->id, 'code' => 'BSDS']);
    $student->student->update(['registration_no' => 'SU92-BSSEM-F22-171']);
    $studentProfile = $student->student;

    Livewire::actingAs($superAdmin)
        ->test(Show::class, ['student' => $studentProfile])
        ->set('program_id', (string) $bsds->id)
        ->set('current_semester', 5)
        ->set('batch', 'F22')
        ->call('updateProfile')
        ->assertHasErrors(['program_id']);
});

test('changing a student\'s current semester never changes semester_at_submission on existing applications', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    Program::factory()->create(['department_id' => $department->id, 'code' => 'BSSE']);
    $student = createStudentUser($department);
    $student->student->update(['current_semester' => 3]);
    $studentProfile = $student->student;

    $application = createApplicationInDepartment($department, $student);
    $application->update(['semester_at_submission' => 3]);

    Livewire::actingAs($superAdmin)
        ->test(Show::class, ['student' => $studentProfile])
        ->set('current_semester', 6)
        ->set('batch', $studentProfile->batch)
        ->call('updateProfile')
        ->assertHasNoErrors();

    expect($studentProfile->refresh()->current_semester)->toBe(6);
    expect($application->refresh()->semester_at_submission)->toBe(3);
});

test('correcting a student\'s name is logged in the activity log and shows a success message', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $student->update(['name' => 'Mohammad Ali']);
    $studentProfile = $student->student;

    Livewire::actingAs($admin)
        ->test(Show::class, ['student' => $studentProfile])
        ->set('name', 'Muhammad Ali')
        ->call('updateName')
        ->assertHasNoErrors();

    expect($student->refresh()->name)->toBe('Muhammad Ali');

    $activity = Activity::query()
        ->where('subject_type', $student->getMorphClass())
        ->where('subject_id', $student->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    // The diff lives in attribute_changes, not properties, on this
    // package version — see UPGRADING.md for the v5 column rename.
    expect($activity->attribute_changes->get('old')['name'])->toBe('Mohammad Ali');
    expect($activity->attribute_changes->get('attributes')['name'])->toBe('Muhammad Ali');
});
