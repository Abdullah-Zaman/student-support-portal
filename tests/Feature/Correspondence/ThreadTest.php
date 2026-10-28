<?php

use App\Actions\Correspondence\QueueCorrespondenceEmail;
use App\Actions\Correspondence\ReplyToCorrespondence;
use App\Actions\Forwarding\ForwardApplication;
use App\Enums\ApplicationStatus;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\ForwardTarget;
use App\Livewire\Correspondence\Thread;
use App\Models\Application;
use App\Models\CorrespondenceAttachment;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use App\Notifications\CorrespondenceReceived;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

/**
 * A department with an Admin Officer, HOD and Dean, and an application
 * the Admin Officer has forwarded to the HOD (so the HOD is in the chain,
 * the Dean is not yet).
 *
 * @return array{admin: User, hod: User, dean: User, application: Application, department: Department}
 */
function correspondenceChain(): array
{
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $dean = createDean($faculty);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin, 'Please advise.', $hod);

    return compact('admin', 'hod', 'dean', 'application', 'department');
}

/**
 * Raw MIME of every email sent through the faked Gmail API, with folded
 * header lines unfolded so a header can be matched on one line.
 *
 * @return array<int, string>
 */
function sentGmailRequests(): array
{
    return Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://gmail.googleapis.com/upload/'))
        ->map(fn (array $pair) => (string) preg_replace("/\r\n[ \t]+/", ' ', $pair[0]->body()))
        ->values()
        ->all();
}

test('nobody can reply on a closed or rejected application', function (ApplicationStatus $status) {
    ['admin' => $admin, 'application' => $application] = correspondenceChain();
    $application->update(['status' => $status]);

    expect($admin->can('reply', $application->conversations()->sole()))->toBeFalse();
})->with([ApplicationStatus::Closed, ApplicationStatus::Rejected]);

test('a conversation of someone else cannot be opened by changing its id', function () {
    ['admin' => $admin, 'hod' => $hod, 'dean' => $dean, 'application' => $application] = correspondenceChain();
    $deanConversation = CorrespondenceConversation::between($application->id, CorrespondenceParty::AdminOfficer, CorrespondenceParty::Dean);
    CorrespondenceMessage::factory()->create([
        'application_id' => $application->id,
        'author_id' => $admin->id,
        'to_party' => CorrespondenceParty::Dean,
        'to_user_id' => $dean->id,
        'body' => 'Secret for the Dean.',
    ]);

    Livewire::actingAs($hod)
        ->test(Thread::class, ['application' => $application])
        ->assertDontSee('Secret for the Dean.')
        ->call('selectConversation', $deanConversation->id)
        ->assertNotFound();
});

test('students, outsiders and other departments cannot view the thread', function (string $viewer) {
    ['application' => $application, 'department' => $department] = correspondenceChain();
    $otherDepartment = Department::factory()->create();

    $user = match ($viewer) {
        'student' => $application->student->user,
        'other admin officer' => createAdminOfficer($otherDepartment),
        'other HOD' => createHod($otherDepartment),
        'Dean not in chain' => User::activeDeanForFaculty($department->faculty_id),
    };

    expect($user->can('viewCorrespondence', $application))->toBeFalse();

    Livewire::actingAs($user)
        ->test(Thread::class, ['application' => $application])
        ->assertForbidden();
})->with(['student', 'other admin officer', 'other HOD', 'Dean not in chain']);

test('the Super Admin cannot open the conversations panel', function () {
    ['application' => $application] = correspondenceChain();

    Livewire::actingAs(createSuperAdmin())
        ->test(Thread::class, ['application' => $application])
        ->assertForbidden();
});

test('an HOD replies with an attachment from their own Gmail and the Admin Officer is notified', function () {
    Notification::fake();
    fakeGmailApi();
    Storage::fake('local');
    ['admin' => $admin, 'hod' => $hod, 'application' => $application] = correspondenceChain();
    connectGmail($hod);

    Livewire::actingAs($hod)
        ->test(Thread::class, ['application' => $application])
        ->set('reply_body', 'Has the student cleared earlier dues?')
        ->set('reply_attachments', [UploadedFile::fake()->create('ledger.pdf', 100, 'application/pdf')])
        ->call('reply')
        ->assertHasNoErrors();

    $reply = CorrespondenceMessage::query()->where('type', CorrespondenceType::Reply)->sole();
    expect($reply->from_user_id)->toBe($hod->id)
        ->and($reply->to_party)->toBe(CorrespondenceParty::AdminOfficer)
        ->and($reply->to_user_id)->toBe($admin->id)
        ->and($reply->email_status)->toBe(CorrespondenceEmailStatus::Sent);

    $attachment = $reply->attachments()->sole();
    Storage::disk('local')->assertExists($attachment->file_path);
    expect($attachment->original_name)->toBe('ledger.pdf')
        ->and($attachment->file_path)->not->toContain('ledger');

    expect(sentGmailRequests()[0])->toContain("To: {$admin->email}", 'Content-Disposition: attachment; name=ledger.pdf');
    Notification::assertSentTo($admin, CorrespondenceReceived::class, fn (CorrespondenceReceived $notification) => $notification->toArray($admin)['message'] === "The HOD replied on {$application->application_no}.");
    expect(Activity::query()->where('subject_type', $reply->getMorphClass())->where('subject_id', $reply->id)->where('event', 'created')->exists())->toBeTrue();
});

test('a reply without text is rejected', function () {
    ['hod' => $hod, 'application' => $application] = correspondenceChain();

    Livewire::actingAs($hod)
        ->test(Thread::class, ['application' => $application])
        ->set('reply_body', '')
        ->call('reply')
        ->assertHasErrors(['reply_body' => 'required']);
});

test('every email of a thread carries the same subject and threading headers, and reuses the sender\'s Gmail thread', function () {
    fakeGmailApi();
    ['admin' => $admin, 'hod' => $hod, 'department' => $department] = correspondenceChain();
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $application->update(['application_no' => 'SC-2026-000124', 'subject' => 'Fee instalment request']);
    connectGmail($admin);
    connectGmail($hod);

    $forward = app(ForwardApplication::class)->handle($application, $admin, ForwardTarget::Hod, 'Please advise.', 'forward-key');
    $hodReply = app(ReplyToCorrespondence::class)->handle($forward->conversation, $hod, 'Dues cleared?', 'hod-reply-key');
    $adminReply = app(ReplyToCorrespondence::class)->handle($forward->conversation, $admin, 'Yes, all cleared.', 'admin-reply-key');

    [$forwardMail, $hodMail, $adminMail] = sentGmailRequests();

    expect($forwardMail)->toContain('Subject: Fwd: Fee instalment request (SC-2026-000124)');

    foreach ([$hodMail, $adminMail] as $mail) {
        expect($mail)->toContain('Subject: Re: Fee instalment request (SC-2026-000124)');
    }

    expect($forwardMail)->toContain("Message-ID: <{$forward->rfc_message_id}>")
        ->not->toContain('In-Reply-To:')
        ->and($hodMail)->toContain(
            "Message-ID: <{$hodReply->rfc_message_id}>",
            "In-Reply-To: <{$forward->rfc_message_id}>",
            "References: <{$forward->rfc_message_id}>",
        )
        ->and($adminMail)->toContain(
            "Message-ID: <{$adminReply->rfc_message_id}>",
            "In-Reply-To: <{$hodReply->rfc_message_id}>",
            "References: <{$forward->rfc_message_id}> <{$hodReply->rfc_message_id}>",
        );

    expect($hodMail)->not->toContain('"threadId"')
        ->and($adminMail)->toContain('{"threadId":"gmail-thread-1"}');
});

test('a reply in a thread Gmail no longer knows is sent as a new thread', function () {
    Http::preventStrayRequests();
    Http::fake([
        'gmail.googleapis.com/upload/*' => Http::sequence()
            ->push(['id' => 'gmail-message-1', 'threadId' => 'gmail-thread-1'])
            ->push(['error' => ['message' => 'Requested entity was not found.']], 404)
            ->push(['id' => 'gmail-message-3', 'threadId' => 'gmail-thread-3']),
    ]);
    ['admin' => $admin, 'department' => $department] = correspondenceChain();
    $application = createApplicationInDepartment($department, createStudentUser($department));
    connectGmail($admin);

    $forward = app(ForwardApplication::class)->handle($application, $admin, ForwardTarget::Hod, 'Please advise.', 'forward-key');
    $reply = app(ReplyToCorrespondence::class)->handle($forward->conversation, $admin, 'Any update?', 'reply-key');

    expect($reply->refresh()->email_status)->toBe(CorrespondenceEmailStatus::Sent)
        ->and($reply->gmail_thread_id)->toBe('gmail-thread-3');
});

test('a double-submitted reply is recorded and emailed once', function () {
    fakeGmailApi();
    ['admin' => $admin, 'hod' => $hod, 'application' => $application] = correspondenceChain();
    connectGmail($hod);
    $replyToCorrespondence = app(ReplyToCorrespondence::class);

    $conversation = $application->conversations()->sole();
    $first = $replyToCorrespondence->handle($conversation, $hod, 'Recommended.', 'same-key');
    $second = $replyToCorrespondence->handle($conversation, $hod, 'Recommended.', 'same-key');

    expect($second->is($first))->toBeTrue()
        ->and(CorrespondenceMessage::query()->where('type', CorrespondenceType::Reply)->count())->toBe(1)
        ->and(sentGmailRequests())->toHaveCount(1)
        ->and($admin->notifications()->count())->toBe(1);
});

test('a double-clicked retry sends the email once', function () {
    fakeGmailApi();
    ['admin' => $admin, 'dean' => $dean, 'application' => $application] = correspondenceChain();
    connectGmail($admin);
    $message = recordForward($application, ForwardTarget::Dean, $admin, 'Please review.', $dean);
    $message->update(['email_status' => CorrespondenceEmailStatus::Failed]);
    $queueCorrespondenceEmail = app(QueueCorrespondenceEmail::class);

    expect($queueCorrespondenceEmail->handle($message))->toBeTrue()
        ->and($queueCorrespondenceEmail->handle($message))->toBeFalse()
        ->and(sentGmailRequests())->toHaveCount(1)
        ->and($message->refresh()->email_status)->toBe(CorrespondenceEmailStatus::Sent);
});

test('the HOD logs the Registrar\'s emailed reply in their private conversation with the Registrar', function () {
    Http::preventStrayRequests();
    ['admin' => $admin, 'hod' => $hod, 'application' => $application] = correspondenceChain();
    recordForward($application, ForwardTarget::Registrar, $hod, 'Please process the instalment.');
    $registrarConversation = CorrespondenceConversation::between($application->id, CorrespondenceParty::Hod, CorrespondenceParty::Registrar);

    Livewire::actingAs($hod)
        ->test(Thread::class, ['application' => $application])
        ->call('selectConversation', $registrarConversation->id)
        ->assertSee('Log external reply')
        ->set('external_body', 'Approved. Two instalments allowed.')
        ->call('logExternalReply')
        ->assertHasNoErrors()
        ->assertSee('Logged by '.$hod->name);

    $logged = CorrespondenceMessage::query()->where('type', CorrespondenceType::LoggedReply)->sole();
    expect($logged->conversation_id)->toBe($registrarConversation->id)
        ->and($logged->from_party)->toBe(CorrespondenceParty::Registrar)
        ->and($logged->from_email)->toBe('registrar@superior.edu.pk')
        ->and($logged->to_party)->toBe(CorrespondenceParty::Hod)
        ->and($logged->to_user_id)->toBe($hod->id)
        ->and($logged->email_status)->toBe(CorrespondenceEmailStatus::NotEmailed);

    Http::assertNothingSent();
    expect($admin->can('view', $registrarConversation))->toBeFalse();
});

test('a Registrar reply can only be logged by the HOD or Dean of that conversation, after a Registrar forward', function () {
    ['admin' => $admin, 'hod' => $hod, 'application' => $application] = correspondenceChain();
    $registrarConversation = CorrespondenceConversation::between($application->id, CorrespondenceParty::Hod, CorrespondenceParty::Registrar);

    expect($hod->can('logExternalReply', $registrarConversation))->toBeFalse();

    recordForward($application, ForwardTarget::Registrar, $hod);

    expect($hod->can('logExternalReply', $registrarConversation))->toBeTrue()
        ->and($admin->can('logExternalReply', $registrarConversation))->toBeFalse()
        ->and(createSuperAdmin()->can('logExternalReply', $registrarConversation))->toBeFalse();

    Livewire::actingAs($admin)
        ->test(Thread::class, ['application' => $application])
        ->set('external_body', 'Made-up registrar reply')
        ->call('logExternalReply')
        ->assertForbidden();
});

test('correspondence attachments are served only to people who can view the thread', function () {
    Storage::fake('local');
    ['admin' => $admin, 'hod' => $hod, 'dean' => $dean, 'application' => $application] = correspondenceChain();
    $message = $application->correspondence()->sole();
    Storage::disk('local')->put('correspondence/abc123.pdf', '%PDF-1.4');
    $attachment = CorrespondenceAttachment::create([
        'correspondence_message_id' => $message->id,
        'application_id' => $application->id,
        'uploaded_by' => $admin->id,
        'original_name' => 'minutes.pdf',
        'file_path' => 'correspondence/abc123.pdf',
        'mime_type' => 'application/pdf',
        'size' => 8,
    ]);

    $this->actingAs($hod)->get(route('correspondence.attachments.show', $attachment))->assertOk()->assertDownload('minutes.pdf');
    $this->actingAs($application->student->user)->get(route('correspondence.attachments.show', $attachment))->assertForbidden();
    $this->actingAs(createHod())->get(route('correspondence.attachments.show', $attachment))->assertForbidden();
    $this->actingAs($dean)->get(route('correspondence.attachments.show', $attachment))->assertForbidden();
    $this->actingAs(createSuperAdmin())->get(route('correspondence.attachments.show', $attachment))->assertForbidden();
});

test('message text is escaped in the thread', function () {
    ['admin' => $admin, 'application' => $application] = correspondenceChain();
    $application->correspondence()->sole()->update(['body' => '<script>alert("x")</script>']);

    Livewire::actingAs($admin)
        ->test(Thread::class, ['application' => $application])
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert("x")</script>', false);
});
