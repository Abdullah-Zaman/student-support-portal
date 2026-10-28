<?php

use App\Actions\Support\CreateSupportRequest;
use App\Enums\ForwardTarget;
use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestStatus;
use App\Livewire\SuperAdmin\Support\Index as AdminIndex;
use App\Livewire\SuperAdmin\Support\Show as AdminShow;
use App\Livewire\Support\Create;
use App\Livewire\Support\Index as StaffIndex;
use App\Livewire\Support\Show as StaffShow;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\SupportRequest;
use App\Models\SupportRequestAttachment;
use App\Models\User;
use App\Notifications\SupportRequestCreated;
use App\Notifications\SupportRequestUpdated;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
    Storage::fake('local');
});

/**
 * @param  array<string, mixed>  $overrides
 */
function sendSupportRequest(User $reporter, array $overrides = [], array $files = []): SupportRequest
{
    return app(CreateSupportRequest::class)->handle($reporter, [
        'category' => 'system_problem',
        'subject' => 'Cannot open an application',
        'message' => 'The page shows an error.',
        ...$overrides,
    ], $files);
}

dataset('reporters', ['admin officer', 'hod', 'dean']);

function reporterFor(string $role, ?Department $department = null): User
{
    $department ??= Department::factory()->create();

    return match ($role) {
        'admin officer' => createAdminOfficer($department),
        'hod' => createHod($department),
        'dean' => createDean($department->faculty),
    };
}

test('a staff member creates a request with screenshots and the Super Admin is notified in the portal and by email', function (string $role) {
    Notification::fake();
    $superAdmin = createSuperAdmin();
    $reporter = reporterFor($role);

    Livewire::actingAs($reporter)
        ->test(Create::class)
        ->set('category', 'my_account')
        ->set('subject', 'I cannot sign in')
        ->set('message', 'My password is rejected.')
        ->set('attachments', [
            UploadedFile::fake()->image('error.png', 300, 200)->size(40),
            UploadedFile::fake()->create('log.pdf', 50, 'application/pdf'),
        ])
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('support.index'));

    $request = SupportRequest::query()->sole();
    expect($request->reporter_id)->toBe($reporter->id)
        ->and($request->category)->toBe(SupportRequestCategory::MyAccount)
        ->and($request->status)->toBe(SupportRequestStatus::Open)
        ->and($request->attachments)->toHaveCount(2);

    foreach ($request->attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->file_path);
        expect($attachment->file_path)->not->toContain('error')->not->toContain('log');
    }

    Notification::assertSentTo($superAdmin, SupportRequestCreated::class, fn ($notification, array $channels) => $channels === ['database', 'mail']);
    Notification::assertNotSentTo($reporter, SupportRequestCreated::class);

    $log = Activity::query()->where('description', 'support request created')->sole();
    expect($log->causer_id)->toBe($reporter->id)->and($log->subject_id)->toBe($request->id);
})->with('reporters');

test('the Super Admin gets a database notification that points to the request', function () {
    $superAdmin = createSuperAdmin();
    $request = sendSupportRequest(createAdminOfficer(Department::factory()->create()));

    $notification = $superAdmin->notifications()->sole();

    expect($notification->data['url'])->toBe(route('super-admin.support.show', $request))
        ->and($notification->data['message'])->toContain('Cannot open an application');
});

test('a reporter sees only their own requests, with status and note', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $mine = SupportRequest::factory()->create(['reporter_id' => $admin->id, 'subject' => 'My own problem', 'status' => SupportRequestStatus::InProgress, 'resolution_note' => 'Looking into it.']);
    SupportRequest::factory()->create(['reporter_id' => $hod->id, 'subject' => 'Someone else problem']);

    Livewire::actingAs($admin)->test(StaffIndex::class)
        ->assertSee('My own problem')
        ->assertDontSee('Someone else problem');

    Livewire::actingAs($admin)->test(StaffShow::class, ['supportRequest' => $mine])
        ->assertSee('In progress')
        ->assertSee('Looking into it.');
});

test('nobody but the reporter and the Super Admin can open a request', function (string $viewer) {
    $department = Department::factory()->create();
    $reporter = createAdminOfficer($department);
    $request = sendSupportRequest($reporter);

    $user = match ($viewer) {
        'other admin officer' => createAdminOfficer($department),
        'hod' => createHod($department),
        'dean' => createDean($department->faculty),
        'student' => createStudentUser($department),
    };

    expect($user->can('view', $request))->toBeFalse();

    $this->actingAs($user)->get(route('support.show', $request))->assertForbidden();
    $this->actingAs($user)->get(route('super-admin.support.show', $request))->assertForbidden();
    $this->actingAs($user)->get(route('super-admin.support.index'))->assertForbidden();
})->with(['other admin officer', 'hod', 'dean', 'student']);

test('students and the Super Admin have no contact form, and guests are sent to log in', function () {
    $department = Department::factory()->create();

    $this->actingAs(createStudentUser($department))->get(route('support.index'))->assertForbidden();
    $this->actingAs(createStudentUser($department))->get(route('support.create'))->assertForbidden();
    $this->actingAs(createSuperAdmin())->get(route('support.create'))->assertForbidden();

    auth()->logout();
    $this->get(route('support.index'))->assertRedirect();
    $this->get(route('super-admin.support.index'))->assertRedirect();
});

test('the Super Admin lists, filters, sets in progress, replies and resolves, and the reporter is told each time', function () {
    Notification::fake();
    $superAdmin = createSuperAdmin();
    $reporter = createAdminOfficer(Department::factory()->create());
    $open = sendSupportRequest($reporter, ['subject' => 'Open one']);
    $other = sendSupportRequest($reporter, ['subject' => 'Account one', 'category' => 'my_account']);
    SupportRequest::factory()->resolved()->create(['reporter_id' => $reporter->id, 'subject' => 'Old resolved one']);

    Livewire::actingAs($superAdmin)->test(AdminIndex::class)
        ->assertSee('Open one')->assertSee('Account one')->assertSee('Old resolved one')
        ->set('status', 'resolved')->assertSee('Old resolved one')->assertDontSee('Open one')
        ->set('status', '')->set('category', 'my_account')->assertSee('Account one')->assertDontSee('Open one');

    $component = Livewire::actingAs($superAdmin)->test(AdminShow::class, ['supportRequest' => $open])
        ->assertSee('The page shows an error.')
        ->call('markInProgress');

    expect($open->refresh()->status)->toBe(SupportRequestStatus::InProgress);
    Notification::assertSentTo($reporter, SupportRequestUpdated::class, fn ($n, array $channels) => $n->event === SupportRequestUpdated::IN_PROGRESS && $channels === ['database', 'mail']);

    $component->set('note', 'Please clear your cache.')->call('reply')->assertHasNoErrors();
    expect($open->refresh()->resolution_note)->toBe('Please clear your cache.')
        ->and($open->status)->toBe(SupportRequestStatus::InProgress);
    Notification::assertSentTo($reporter, SupportRequestUpdated::class, fn ($n) => $n->event === SupportRequestUpdated::REPLIED);

    $component->set('note', '')->call('resolve')->assertHasErrors(['note' => 'required']);
    expect($open->refresh()->status)->toBe(SupportRequestStatus::InProgress);

    $component->set('note', 'Fixed the account.')->call('resolve')->assertHasNoErrors();
    $open->refresh();
    expect($open->status)->toBe(SupportRequestStatus::Resolved)
        ->and($open->resolution_note)->toBe('Fixed the account.')
        ->and($open->resolved_by)->toBe($superAdmin->id)
        ->and($open->resolved_at)->not->toBeNull();
    Notification::assertSentTo($reporter, SupportRequestUpdated::class, fn ($n) => $n->event === SupportRequestUpdated::RESOLVED);

    expect(Activity::query()->where('subject_id', $open->id)->where('description', 'support request status changed')->exists())->toBeTrue()
        ->and(Activity::query()->where('subject_id', $open->id)->where('description', 'support request resolved')->exists())->toBeTrue();

    $component->call('markInProgress')->assertForbidden();
    expect($other->refresh()->status)->toBe(SupportRequestStatus::Open);
});

test('the reporter and nobody else can use the Super Admin actions', function () {
    $reporter = createAdminOfficer(Department::factory()->create());
    $request = sendSupportRequest($reporter);

    Livewire::actingAs($reporter)->test(AdminShow::class, ['supportRequest' => $request])->assertForbidden();
    expect($reporter->can('update', $request))->toBeFalse()
        ->and(SupportRequest::query()->sole()->status)->toBe(SupportRequestStatus::Open);
});

test('attachments are served only to the reporter and the Super Admin', function () {
    $department = Department::factory()->create();
    $reporter = createAdminOfficer($department);
    $request = sendSupportRequest($reporter, files: [UploadedFile::fake()->image('shot.png', 100, 100)->size(10)]);
    $attachment = $request->attachments()->sole();

    $this->actingAs($reporter)->get(route('support.attachments.show', $attachment))->assertOk()->assertDownload('shot.png');
    $this->actingAs(createSuperAdmin())->get(route('support.attachments.show', $attachment))->assertOk();
    $this->actingAs(createAdminOfficer($department))->get(route('support.attachments.show', $attachment))->assertForbidden();
    $this->actingAs(createHod($department))->get(route('support.attachments.show', $attachment))->assertForbidden();
    $this->actingAs(createStudentUser($department))->get(route('support.attachments.show', $attachment))->assertForbidden();

    auth()->logout();
    $this->get(route('support.attachments.show', $attachment))->assertRedirect();
    expect(SupportRequestAttachment::query()->count())->toBe(1);
});

test('files must be images or PDFs, at most 3, at most 5 MB each', function () {
    $component = Livewire::actingAs(createAdminOfficer(Department::factory()->create()))
        ->test(Create::class)
        ->set('subject', 'Files')
        ->set('message', 'Testing files.');

    $component->set('attachments', [UploadedFile::fake()->create('notes.docx', 10, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document')])
        ->call('save')->assertHasErrors(['attachments.0' => 'mimes']);

    $component->set('attachments', [UploadedFile::fake()->create('renamed.png', 10, 'text/plain')])
        ->call('save')->assertHasErrors(['attachments.0' => 'mimes']);

    $component->set('attachments', [UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])
        ->call('save')->assertHasErrors(['attachments.0'])->assertSee('Each file can be at most 5 MB.');

    $component->set('attachments', array_map(fn (int $i) => UploadedFile::fake()->image("shot{$i}.png")->size(10), [1, 2, 3, 4]))
        ->call('save')->assertHasErrors(['attachments' => 'max']);

    expect(SupportRequest::query()->count())->toBe(0);
});

test('the form validates its fields and only links an application or student the reporter may open', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['application_no' => 'SC-2026-000901']);
    $otherApplication = createApplicationInDepartment(Department::factory()->create(), createStudentUser());
    $otherApplication->update(['application_no' => 'SC-2026-000902']);

    Livewire::actingAs($admin)->test(Create::class)
        ->set('category', 'nonsense')->set('subject', '')->set('message', '')
        ->call('save')->assertHasErrors(['category', 'subject' => 'required', 'message' => 'required']);

    Livewire::actingAs($admin)->test(Create::class)
        ->set('subject', 'Linked')->set('message', 'About an application.')
        ->set('application_no', $otherApplication->application_no)->call('save')
        ->assertHasErrors(['application_no'])
        ->set('application_no', '')->set('student_registration_no', 'SU00-NOPE-F22-1')->call('save')
        ->assertHasErrors(['student_registration_no'])
        ->set('student_registration_no', '')->set('application_no', $application->application_no)
        ->set('student_registration_no', $student->student->registration_no)->call('save')
        ->assertHasNoErrors();

    $request = SupportRequest::query()->sole();
    expect($request->application_id)->toBe($application->id)->and($request->student_id)->toBe($student->student->id);

    Livewire::actingAs($hod)->test(Create::class)
        ->set('subject', 'HOD link')->set('message', 'Not forwarded to me.')
        ->set('application_no', $application->application_no)->call('save')
        ->assertHasErrors(['application_no']);
});

test('creating requests is rate limited', function () {
    $admin = createAdminOfficer(Department::factory()->create());

    foreach (range(1, 5) as $number) {
        Livewire::actingAs($admin)->test(Create::class)->set('subject', "Request {$number}")->set('message', 'Hello.')->call('save')->assertHasNoErrors();
    }

    Livewire::actingAs($admin)->test(Create::class)->set('subject', 'One too many')->set('message', 'Hello.')->call('save')
        ->assertHasErrors(['subject']);

    expect(SupportRequest::query()->count())->toBe(5);
});

test('the request does not pull in staff conversations, and its text is escaped', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin, 'PRIVATE-CONVERSATION-TEXT', $hod);

    $request = sendSupportRequest($admin, [
        'subject' => '<script>alert("x")</script>',
        'message' => 'Please look at this one.',
        'application_id' => $application->id,
    ]);

    Livewire::actingAs(createSuperAdmin())->test(AdminShow::class, ['supportRequest' => $request])
        ->assertDontSee('PRIVATE-CONVERSATION-TEXT')
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert("x")</script>', false);

    expect(CorrespondenceMessage::query()->count())->toBe(1);
});

test('the sidebar links Contact Super Admin for staff and shows the open count to the Super Admin', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    sendSupportRequest($admin);
    sendSupportRequest($admin, ['subject' => 'Second']);
    SupportRequest::factory()->resolved()->create(['reporter_id' => $admin->id]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('Contact Super Admin')->assertDontSee('Support requests');
    $this->actingAs(createHod($department))->get(route('hod.dashboard'))->assertSee('Contact Super Admin');
    $this->actingAs(createSuperAdmin())->get(route('super-admin.dashboard'))
        ->assertSeeHtml('data-test="sidebar-support-requests"')
        ->assertDontSee('Contact Super Admin');

    expect(SupportRequest::query()->where('status', SupportRequestStatus::Open)->count())->toBe(2);
});
