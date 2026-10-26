<?php

use App\Actions\Forwarding\AddRemark;
use App\Actions\Forwarding\ForwardApplication;
use App\Enums\ForwardTarget;
use App\Enums\RemarkType;
use App\Models\Department;

beforeEach(function () {
    createRoles();
});

test('a student sees where their application was forwarded but never the note or any remark', function () {
    fakeGmailApi();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    connectGmail($admin);
    $hod = createHod($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    app(ForwardApplication::class)->handle($application, $admin, ForwardTarget::Hod, 'Confidential forward note for the HOD');
    app(AddRemark::class)->handle($application, $hod, RemarkType::NotRecommended, 'Confidential HOD remark text');

    $this->actingAs($student)
        ->get(route('student.applications.show', $application))
        ->assertOk()
        ->assertSee('Forwarded to the HOD.')
        ->assertDontSee('Confidential forward note for the HOD')
        ->assertDontSee('Confidential HOD remark text')
        ->assertDontSee('Not recommended');

    $this->actingAs($student)
        ->get(route('student.notifications.index'))
        ->assertOk()
        ->assertSee('has been forwarded to the HOD for review.')
        ->assertDontSee('Confidential');

    expect($student->notifications()->get()->pluck('data')->toJson())
        ->not->toContain('Confidential');
});

test('a student is never allowed to view the forward chain', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    expect($student->can('viewForwardChain', $application))->toBeFalse()
        ->and($student->can('remark', $application))->toBeFalse();
});
