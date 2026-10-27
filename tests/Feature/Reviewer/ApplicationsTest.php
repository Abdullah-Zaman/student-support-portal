<?php

use App\Enums\ForwardTarget;
use App\Enums\RemarkType;
use App\Livewire\Reviewer\Applications\Index;
use App\Livewire\Reviewer\Applications\Show;
use App\Models\ApplicationAttachment;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\InternalNote;
use App\Notifications\CorrespondenceReceived;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an HOD lists only their own department\'s applications forwarded to the HOD', function () {
    $department = Department::factory()->create();
    $hod = createHod($department);
    $admin = createAdminOfficer($department);
    $forwarded = createApplicationInDepartment($department, createStudentUser($department));
    $forwarded->update(['subject' => 'Forwarded to our HOD']);
    recordForward($forwarded, ForwardTarget::Hod, $admin);
    $notForwarded = createApplicationInDepartment($department, createStudentUser($department));
    $notForwarded->update(['subject' => 'Still with the Admin Office']);
    $otherDepartment = Department::factory()->create();
    $otherApplication = createApplicationInDepartment($otherDepartment, createStudentUser($otherDepartment));
    $otherApplication->update(['subject' => 'Other department forward']);
    recordForward($otherApplication, ForwardTarget::Hod, createAdminOfficer($otherDepartment));

    Livewire::actingAs($hod)
        ->test(Index::class)
        ->assertSee('Forwarded to our HOD')
        ->assertDontSee('Still with the Admin Office')
        ->assertDontSee('Other department forward');
});

test('an HOD cannot open another department\'s application, even one forwarded to its HOD', function () {
    $hod = createHod();
    $otherDepartment = Department::factory()->create();
    $application = createApplicationInDepartment($otherDepartment, createStudentUser($otherDepartment));
    recordForward($application, ForwardTarget::Hod, createAdminOfficer($otherDepartment));

    $this->actingAs($hod)
        ->get(route('hod.applications.show', $application))
        ->assertForbidden();
});

test('an HOD cannot open their department\'s application before it is forwarded to them', function () {
    $department = Department::factory()->create();
    $application = createApplicationInDepartment($department, createStudentUser($department));

    $this->actingAs(createHod($department))
        ->get(route('hod.applications.show', $application))
        ->assertForbidden();
});

test('a Dean sees only applications forwarded to the Dean from their own faculty', function () {
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $dean = createDean($faculty);
    $admin = createAdminOfficer($department);
    $toDean = createApplicationInDepartment($department, createStudentUser($department));
    $toDean->update(['subject' => 'Forwarded to the Dean']);
    recordForward($toDean, ForwardTarget::Dean, $admin);
    $onlyHod = createApplicationInDepartment($department, createStudentUser($department));
    $onlyHod->update(['subject' => 'Only with the HOD']);
    recordForward($onlyHod, ForwardTarget::Hod, $admin);
    $otherDepartment = Department::factory()->create();
    $otherFaculty = createApplicationInDepartment($otherDepartment, createStudentUser($otherDepartment));
    $otherFaculty->update(['subject' => 'Other faculty Dean forward']);
    recordForward($otherFaculty, ForwardTarget::Dean, createAdminOfficer($otherDepartment));

    Livewire::actingAs($dean)
        ->test(Index::class)
        ->assertSee('Forwarded to the Dean')
        ->assertDontSee('Only with the HOD')
        ->assertDontSee('Other faculty Dean forward');

    $this->actingAs($dean)->get(route('dean.applications.show', $otherFaculty))->assertForbidden();
    $this->actingAs($dean)->get(route('dean.applications.show', $toDean))->assertOk();
});

test('the reviewer view shows the correspondence but never internal notes or priority', function () {
    $department = Department::factory()->create();
    $hod = createHod($department);
    $admin = createAdminOfficer($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin, 'Fee instalment request, please advise.');
    InternalNote::create(['application_id' => $application->id, 'admin_id' => $admin->id, 'body' => 'Secret admin-only note']);

    $this->actingAs($hod)
        ->get(route('hod.applications.show', $application))
        ->assertOk()
        ->assertSee('Fee instalment request, please advise.')
        ->assertDontSee('Secret admin-only note')
        ->assertDontSee('Priority');
});

test('an HOD adds a remark and the admin officers are notified', function () {
    Notification::fake();
    $department = Department::factory()->create();
    $hod = createHod($department);
    $admin = createAdminOfficer($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin);

    Livewire::actingAs($hod)
        ->test(Show::class, ['application' => $application])
        ->set('remark_type', 'recommended')
        ->set('remark_body', 'Good record, I support it.')
        ->call('addRemark')
        ->assertHasNoErrors();

    $remark = CorrespondenceMessage::query()->where('type', 'remark')->sole();
    expect($remark->remark_type)->toBe(RemarkType::Recommended)
        ->and($remark->from_user_id)->toBe($hod->id)
        ->and($remark->to_user_id)->toBe($admin->id)
        ->and($remark->body)->toBe('Good record, I support it.');

    Notification::assertSentTo($admin, CorrespondenceReceived::class);
});

test('a comment remark needs text', function () {
    $department = Department::factory()->create();
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, createAdminOfficer($department));

    Livewire::actingAs($hod)
        ->test(Show::class, ['application' => $application])
        ->set('remark_type', 'comment')
        ->call('addRemark')
        ->assertHasErrors(['remark_body' => 'required']);
});

test('an HOD can download attachments only of applications forwarded to them', function () {
    Storage::fake('local');
    $department = Department::factory()->create();
    $hod = createHod($department);
    $student = createStudentUser($department);
    $forwarded = createApplicationInDepartment($department, $student);
    recordForward($forwarded, ForwardTarget::Hod, createAdminOfficer($department));
    $notForwarded = createApplicationInDepartment($department, $student);
    $allowed = ApplicationAttachment::factory()->create(['application_id' => $forwarded->id, 'uploaded_by' => $student->id]);
    $denied = ApplicationAttachment::factory()->create(['application_id' => $notForwarded->id, 'uploaded_by' => $student->id]);
    Storage::disk('local')->put($allowed->file_path, 'pdf');
    Storage::disk('local')->put($denied->file_path, 'pdf');

    $this->actingAs($hod)->get(route('applications.attachments.show', $allowed))->assertOk();
    $this->actingAs($hod)->get(route('applications.attachments.show', $denied))->assertForbidden();
});
