<?php

use App\Actions\Forwarding\ForwardApplication;
use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\ForwardTarget;
use App\Livewire\Admin\Applications\Show as AdminShow;
use App\Livewire\Reviewer\Applications\Show as ReviewerShow;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Setting;
use App\Notifications\ApplicationForwardedForReview;
use App\Notifications\ApplicationForwardedOnward;
use App\Notifications\CorrespondenceReceived;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

test('an admin officer forwards an application to their HOD without changing its status', function () {
    Notification::fake();
    fakeGmailApi();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    connectGmail($admin);
    $hod = createHod($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['status' => ApplicationStatus::UnderReview]);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please check the fee instalment request.')
        ->call('forward')
        ->assertHasNoErrors();

    $forward = CorrespondenceMessage::query()->sole();
    expect($forward->to_party)->toBe(CorrespondenceParty::Hod)
        ->and($forward->to_user_id)->toBe($hod->id)
        ->and($forward->from_user_id)->toBe($admin->id)
        ->and($forward->email_status)->toBe(CorrespondenceEmailStatus::Sent)
        ->and($application->refresh()->status)->toBe(ApplicationStatus::UnderReview)
        ->and($application->forwarded_to)->toBe(ForwardTarget::Hod);

    $event = $application->events()->where('event_type', ApplicationEventType::Forwarded)->sole();
    expect($event->visible_to_student)->toBeTrue()
        ->and($event->timelineDescription())->toBe('Forwarded to the HOD.');

    Notification::assertSentTo($hod, CorrespondenceReceived::class);
    Notification::assertSentTo($student, ApplicationForwardedForReview::class, function (ApplicationForwardedForReview $notification, array $channels) use ($student, $application) {
        return $channels === ['database']
            && $notification->toArray($student)['message'] === "Your application {$application->application_no} has been forwarded to the HOD for review.";
    });
    Notification::assertNothingSentTo($admin);

    expect(Activity::query()->where('subject_type', $forward->getMorphClass())->where('subject_id', $forward->id)->exists())->toBeTrue();
});

test('a forward is blocked with a clear message when the department has no active HOD', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    connectGmail($admin);
    $suspendedHod = createHod($department);
    $suspendedHod->update(['status' => 'suspended']);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->assertSee('No active HOD for this department.')
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward')
        ->assertHasErrors(['forward_target']);

    expect(CorrespondenceMessage::query()->count())->toBe(0);
});

test('a forward to the Dean is blocked when the faculty has no active Dean', function () {
    $department = Department::factory()->create();
    $application = createApplicationInDepartment($department, createStudentUser($department));

    expect(app(ForwardApplication::class)->unavailableReason($application, ForwardTarget::Dean))->toBe('No active Dean for this faculty.');
});

test('an HOD forwards to the Registrar by email and the admin officers are told', function () {
    Notification::fake();
    fakeGmailApi();
    Setting::set(ForwardApplication::REGISTRAR_EMAIL_SETTING, 'registrar@superior.edu.pk');
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    connectGmail($hod);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin);

    Livewire::actingAs($hod)
        ->test(ReviewerShow::class, ['application' => $application])
        ->set('forward_target', 'registrar')
        ->set('forward_note', 'Recommended. Please process.')
        ->call('forward')
        ->assertHasNoErrors();

    $forward = CorrespondenceMessage::query()->where('to_party', CorrespondenceParty::Registrar)->sole();
    expect($forward->email_status)->toBe(CorrespondenceEmailStatus::Sent)
        ->and($forward->to_email)->toBe('registrar@superior.edu.pk')
        ->and($forward->to_user_id)->toBeNull()
        ->and($application->refresh()->forwarded_to)->toBe(ForwardTarget::Registrar);

    Notification::assertSentTo($admin, ApplicationForwardedOnward::class);
});

test('a forward to the Registrar is blocked until the Registrar email is set', function () {
    $department = Department::factory()->create();
    $hod = createHod($department);
    connectGmail($hod);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, createAdminOfficer($department));

    Livewire::actingAs($hod)
        ->test(ReviewerShow::class, ['application' => $application])
        ->assertSee('The Registrar email is not set.')
        ->set('forward_target', 'registrar')
        ->set('forward_note', 'Please process.')
        ->call('forward')
        ->assertHasErrors(['forward_target']);
});

test('only the allowed forwarding paths are permitted', function (string $sender, ForwardTarget $target, bool $allowed) {
    Setting::set(ForwardApplication::REGISTRAR_EMAIL_SETTING, 'registrar@superior.edu.pk');
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $dean = createDean($faculty);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    recordForward($application, ForwardTarget::Hod, $admin);
    recordForward($application, ForwardTarget::Dean, $hod);

    $user = ['admin' => $admin, 'hod' => $hod, 'dean' => $dean, 'student' => $student, 'super admin' => createSuperAdmin()][$sender];

    expect($user->can('forward', [$application, $target]))->toBe($allowed);
})->with([
    'admin to HOD' => ['admin', ForwardTarget::Hod, true],
    'admin to Dean' => ['admin', ForwardTarget::Dean, true],
    'admin to Registrar' => ['admin', ForwardTarget::Registrar, false],
    'HOD to Dean' => ['hod', ForwardTarget::Dean, true],
    'HOD to Registrar' => ['hod', ForwardTarget::Registrar, true],
    'HOD to HOD' => ['hod', ForwardTarget::Hod, false],
    'Dean to Registrar' => ['dean', ForwardTarget::Registrar, true],
    'Dean to HOD' => ['dean', ForwardTarget::Hod, false],
    'Dean to Dean' => ['dean', ForwardTarget::Dean, false],
    'student to HOD' => ['student', ForwardTarget::Hod, false],
    'super admin to HOD' => ['super admin', ForwardTarget::Hod, false],
]);

test('an illegal forward path is rejected by the action', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    Setting::set(ForwardApplication::REGISTRAR_EMAIL_SETTING, 'registrar@superior.edu.pk');

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'registrar')
        ->set('forward_note', 'Skipping the HOD.')
        ->call('forward')
        ->assertForbidden();

    expect(CorrespondenceMessage::query()->count())->toBe(0);
});

test('an HOD cannot forward an application that was not forwarded to them', function () {
    $department = Department::factory()->create();
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    expect($hod->can('forward', [$application, ForwardTarget::Dean]))->toBeFalse();
});

test('a closed or rejected application cannot be forwarded', function (ApplicationStatus $status) {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $application->update(['status' => $status]);

    expect($admin->can('forward', [$application, ForwardTarget::Hod]))->toBeFalse();
})->with([ApplicationStatus::Closed, ApplicationStatus::Rejected]);

test('an admin officer cannot forward another department\'s application', function () {
    $department = Department::factory()->create();
    $application = createApplicationInDepartment($department, createStudentUser($department));

    expect(createAdminOfficer()->can('forward', [$application, ForwardTarget::Hod]))->toBeFalse();
});
