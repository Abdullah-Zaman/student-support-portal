<?php

use App\Enums\ApplicationStatus;
use App\Enums\MessageType;
use App\Enums\UserStatus;
use App\Livewire\Admin\Applications\Show as AdminShow;
use App\Livewire\Student\Applications\Create;
use App\Livewire\Student\Applications\Show as StudentShow;
use App\Models\ApplicationCategory;
use App\Models\ApplicationMessage;
use App\Models\Department;
use App\Notifications\AdminMessageReceived;
use App\Notifications\ApplicationClosed;
use App\Notifications\ApplicationInfoRequested;
use App\Notifications\ApplicationRejected;
use App\Notifications\ApplicationResolved;
use App\Notifications\ApplicationStatusChanged;
use App\Notifications\ApplicationSubmitted;
use App\Notifications\NewApplicationSubmitted;
use App\Notifications\StudentRespondedToRequest;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('submitting an application notifies the student and every admin officer in the department', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $admin = createAdminOfficer($department);
    $otherDepartmentAdmin = createAdminOfficer();
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Fee issue')
        ->set('body', 'Details about the fee issue.')
        ->call('submit')
        ->assertHasNoErrors();

    Notification::assertSentTo($student, ApplicationSubmitted::class);
    Notification::assertSentTo($admin, NewApplicationSubmitted::class);
    Notification::assertNotSentTo($otherDepartmentAdmin, NewApplicationSubmitted::class);
});

test('changing status notifies the student', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('status_input', 'under_review')
        ->call('updateStatus')
        ->assertHasNoErrors();

    Notification::assertSentTo($student, ApplicationStatusChanged::class);
});

test('resolving notifies the student with the resolution note', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('resolution_note', 'Fee challan corrected.')
        ->call('resolve')
        ->assertHasNoErrors();

    Notification::assertSentTo($student, ApplicationResolved::class, function ($notification) {
        return $notification->resolutionNote === 'Fee challan corrected.';
    });
});

test('rejecting notifies the student with the rejection reason', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('rejection_reason', 'Outside department scope.')
        ->call('reject')
        ->assertHasNoErrors();

    Notification::assertSentTo($student, ApplicationRejected::class, function ($notification) {
        return $notification->rejectionReason === 'Outside department scope.';
    });
});

test('closing notifies the student', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->call('close')
        ->assertHasNoErrors();

    Notification::assertSentTo($student, ApplicationClosed::class);
});

test('sending a message notifies the student', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('message_body', 'Please share your fee challan number.')
        ->call('sendMessage')
        ->assertHasNoErrors();

    Notification::assertSentTo($student, AdminMessageReceived::class);
});

test('sending an info or document request notifies the student', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('request_body', 'Please upload your fee challan.')
        ->call('sendInfoRequest')
        ->assertHasNoErrors();

    Notification::assertSentTo($student, ApplicationInfoRequested::class);
});

test('a student responding notifies every admin officer in the department', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['status' => ApplicationStatus::InfoRequired]);

    ApplicationMessage::create([
        'application_id' => $application->id,
        'sender_id' => $admin->id,
        'type' => MessageType::InfoRequest->value,
        'body' => 'Please upload your fee challan.',
    ]);

    Livewire::actingAs($student)
        ->test(StudentShow::class, ['application' => $application])
        ->set('response_body', 'Here is the challan.')
        ->call('respond')
        ->assertHasNoErrors();

    Notification::assertSentTo($admin, StudentRespondedToRequest::class);
});

test('changing priority never sends a notification', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('priority_input', 'urgent')
        ->call('updatePriority')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

test('adding an internal note never sends a notification', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('new_note', 'Called the student.')
        ->call('addNote')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
});

test('a suspended student never gets mail, only the database notification', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $student->update(['status' => UserStatus::Suspended]);
    $application = createApplicationInDepartment($department, $student);

    $notification = new ApplicationClosed($application);

    expect($notification->via($student))->toBe(['database']);
});

test('an active student gets both database and mail channels', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    $notification = new ApplicationClosed($application);

    expect($notification->via($student))->toBe(['database', 'mail']);
});

test('a student notification mail is branded with the subject prefix, reply-to and a button to the application', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    $mail = (new ApplicationClosed($application))->toMail($student);

    expect($mail->subject)->toBe(config('mail.subject_prefix').' '.__('Application closed'));
    expect($mail->replyTo[0][0])->toBe(config('mail.reply_to.address'));
    expect($mail->actionUrl)->toBe(route('student.applications.show', $application));
    expect($mail->outroLines)->toContain('This is an automated email. Replies to this address are not received. For any query, log in to the portal.');
});

test('the branded mail layout actually renders, with the logo and a view-application button', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    $html = (string) (new ApplicationClosed($application))->toMail($student)->render();

    expect($html)->toContain('superior-logo-white.png');
    expect($html)->toContain(__('View application'));
    expect($html)->toContain(route('student.applications.show', $application));
});
