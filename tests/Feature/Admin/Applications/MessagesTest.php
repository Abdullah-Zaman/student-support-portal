<?php

use App\Enums\ApplicationStatus;
use App\Enums\MessageType;
use App\Livewire\Admin\Applications\Show;
use App\Livewire\Student\Applications\Show as StudentShow;
use App\Models\ApplicationEvent;
use App\Models\ApplicationMessage;
use App\Models\Department;
use App\Notifications\ApplicationClosed;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an admin officer can send a free-text message', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('message_body', 'Please share your fee challan number.')
        ->call('sendMessage')
        ->assertHasNoErrors();

    $message = ApplicationMessage::query()->where('application_id', $application->id)->firstOrFail();
    expect($message->type)->toBe(MessageType::Message);
    expect($message->sender_id)->toBe($admin->id);
    expect($message->body)->toBe('Please share your fee challan number.');

    // No separate application_event for the message — it's represented
    // once, by the message row itself, on the unified timeline.
    expect(ApplicationEvent::query()->where('application_id', $application->id)->count())->toBe(0);
});

test('an admin officer cannot send a message once the application is closed or rejected', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['status' => ApplicationStatus::Closed, 'closed_at' => now()]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('message_body', 'Hello')
        ->call('sendMessage')
        ->assertForbidden();
});

test('sending an info request sets the status to info_required', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('request_body', 'Please upload your fee challan.')
        ->call('sendInfoRequest')
        ->assertHasNoErrors();

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::InfoRequired);

    $message = ApplicationMessage::query()->where('application_id', $application->id)->firstOrFail();
    expect($message->type)->toBe(MessageType::InfoRequest);
    expect($message->isOpen())->toBeTrue();
});

test('sending a document request sets the status to info_required', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('request_body', 'Please upload your medical certificate.')
        ->call('sendDocumentRequest')
        ->assertHasNoErrors();

    $application->refresh();
    expect($application->status)->toBe(ApplicationStatus::InfoRequired);

    $message = ApplicationMessage::query()->where('application_id', $application->id)->firstOrFail();
    expect($message->type)->toBe(MessageType::DocumentRequest);
});

test('only one open request is allowed at a time', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    $component = Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('request_body', 'First request.')
        ->call('sendInfoRequest')
        ->assertHasNoErrors();

    $component->set('request_body', 'Second request.')
        ->call('sendDocumentRequest')
        ->assertHasErrors(['request_body']);

    expect(ApplicationMessage::query()->where('application_id', $application->id)->count())->toBe(1);
});

test('messages and requests are capped at 1000 characters', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('message_body', str_repeat('a', 1001))
        ->call('sendMessage')
        ->assertHasErrors(['message_body' => 'max']);
});

test('changing status while a request is open automatically cancels it', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('request_body', 'Please upload your fee challan.')
        ->call('sendInfoRequest')
        ->assertHasNoErrors();

    $request = ApplicationMessage::query()->where('application_id', $application->id)->firstOrFail();
    expect($request->isOpen())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application->refresh()])
        ->set('status_input', 'under_review')
        ->call('updateStatus')
        ->assertHasNoErrors();

    expect($request->refresh()->cancelled_at)->not->toBeNull();
    expect($request->isOpen())->toBeFalse();
});

test('resolving, rejecting or closing while a request is open automatically cancels it', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    foreach (['resolve', 'reject', 'close'] as $action) {
        $student = createStudentUser($department);
        $application = createApplicationInDepartment($department, $student);

        $component = Livewire::actingAs($admin)
            ->test(Show::class, ['application' => $application])
            ->set('request_body', 'Please upload your fee challan.')
            ->call('sendInfoRequest')
            ->assertHasNoErrors();

        $request = ApplicationMessage::query()->where('application_id', $application->id)->firstOrFail();

        $fresh = Livewire::actingAs($admin)->test(Show::class, ['application' => $application->refresh()]);

        match ($action) {
            'resolve' => $fresh->set('resolution_note', 'Done.')->call('resolve'),
            'reject' => $fresh->set('rejection_reason', 'Not valid.')->call('reject'),
            'close' => $fresh->call('close'),
        };

        $fresh->assertHasNoErrors();

        expect($request->refresh()->cancelled_at)->not->toBeNull();
    }
});

test('a message typed while closing an application is saved and sent with the closure notification', function () {
    Notification::fake();

    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['status' => ApplicationStatus::Resolved, 'resolved_at' => now()]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->set('message_body', 'Your fee has been adjusted.')
        ->call('close')
        ->assertHasNoErrors();

    expect($application->messages()->where('body', 'Your fee has been adjusted.')->count())->toBe(1);

    Notification::assertSentTo(
        $student,
        ApplicationClosed::class,
        fn (ApplicationClosed $notification) => $notification->body === 'Your fee has been adjusted.',
    );

    Livewire::actingAs($student)
        ->test(StudentShow::class, ['application' => $application])
        ->assertSee('Your fee has been adjusted.');
});
