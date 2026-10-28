<?php

use App\Actions\Correspondence\ReplyToCorrespondence;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\ForwardTarget;
use App\Livewire\Admin\Applications\Show as AdminShow;
use App\Livewire\Correspondence\Thread;
use App\Livewire\Reviewer\Applications\Show as ReviewerShow;
use App\Models\ApplicationAttachment;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\GoogleAccount;
use App\Support\Gmail\GmailSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

/**
 * The MIME message of every email sent through the faked Gmail API: raw
 * (for headers) or with the quoted-printable body text decoded.
 *
 * @return array<int, string>
 */
function sentGmailMessages(bool $raw = false): array
{
    return Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://gmail.googleapis.com/upload/'))
        ->map(fn (array $pair) => $raw ? $pair[0]->body() : quoted_printable_decode($pair[0]->body()))
        ->values()
        ->all();
}

test('without a connected Gmail a forward is saved and waits to be emailed', function () {
    Http::preventStrayRequests();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward')
        ->assertHasNoErrors();

    $forward = CorrespondenceMessage::query()->sole();
    expect($forward->email_status)->toBe(CorrespondenceEmailStatus::AwaitingGmail)
        ->and($application->refresh()->forwarded_to)->toBe(ForwardTarget::Hod);
    Http::assertNothingSent();

    Livewire::actingAs($admin)
        ->test(Thread::class, ['application' => $application])
        ->assertSee('Connect your Gmail. Your messages are saved in the portal')
        ->assertSee('Pending: connect Gmail to send');
});

test('a forward from someone whose Gmail needs reconnecting is saved and waits too', function () {
    Http::preventStrayRequests();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    GoogleAccount::factory()->needsReconnect()->create(['user_id' => $admin->id]);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward')
        ->assertHasNoErrors();

    expect(CorrespondenceMessage::query()->sole()->email_status)->toBe(CorrespondenceEmailStatus::AwaitingGmail);
    Http::assertNothingSent();

    Livewire::actingAs($admin)
        ->test(Thread::class, ['application' => $application])
        ->assertSee('Reconnect your Gmail. Your messages are saved in the portal');
});

test('pending messages are emailed automatically once the sender connects Gmail', function () {
    fakeGmailApi();
    $department = Department::factory()->create();
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, createAdminOfficer($department));
    $forward = recordForward($application, ForwardTarget::Registrar, $hod);
    $forward->update(['email_status' => CorrespondenceEmailStatus::AwaitingGmail]);

    Socialite::fake('google', GoogleUser::fake([
        'id' => 'google-123',
        'email' => $hod->email,
        'token' => 'access-token-abc',
        'refreshToken' => 'refresh-token-xyz',
        'expiresIn' => 3599,
        'approvedScopes' => ['openid', 'email', 'profile', GoogleAccount::GMAIL_SEND_SCOPE],
    ]));

    $this->actingAs($hod)
        ->withSession(['google_oauth_intent' => 'connect'])
        ->get(route('google.callback'))
        ->assertRedirect(route('profile.edit'));

    expect($forward->refresh()->email_status)->toBe(CorrespondenceEmailStatus::Sent)
        ->and(sentGmailMessages())->toHaveCount(1)
        ->and(sentGmailMessages()[0])->toContain('To: registrar@superior.edu.pk');
});

test('a forward is a personal-looking email with the sender\'s note, signature, the forwarded application and the student\'s files, and nothing from other conversations', function () {
    fakeGmailApi();
    Storage::fake('local');
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $hod->update(['email_signature' => "Dr. Hina Malik\nHead of Department, SE"]);
    connectGmail($hod);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['application_no' => 'SC-2026-000124', 'subject' => 'Fee instalment request']);
    Storage::disk('local')->put('attachments/fee-slip.pdf', '%PDF-1.4 fee slip');
    ApplicationAttachment::factory()->create([
        'application_id' => $application->id,
        'uploaded_by' => $student->id,
        'original_name' => 'fee-slip.pdf',
        'file_path' => 'attachments/fee-slip.pdf',
        'size' => 17,
    ]);
    recordForward($application, ForwardTarget::Hod, $admin, 'Private note from the Admin Office.', $hod);
    $dean = createDean($department->faculty);

    Livewire::actingAs($hod)
        ->test(ReviewerShow::class, ['application' => $application])
        ->set('forward_target', 'dean')
        ->set('forward_note', 'Recommended for a fee waiver.')
        ->call('forward')
        ->assertHasNoErrors();

    $forward = CorrespondenceMessage::query()->where('to_party', CorrespondenceParty::Dean)->sole();
    expect($forward->email_status)->toBe(CorrespondenceEmailStatus::Sent)
        ->and($forward->gmail_thread_id)->toBe('gmail-thread-1');

    $messages = sentGmailMessages();
    $raw = sentGmailMessages(raw: true)[0];
    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain(
            "To: {$dean->email}",
            "Subject: Fwd: {$application->subject} (SC-2026-000124)",
            "Dear {$dean->name},",
            'Recommended for a fee waiver.',
            'Dr. Hina Malik',
            'Head of Department, SE',
            '---------- Forwarded application ----------',
            "From: {$student->name} ({$student->student->registration_no}",
            'Subject: '.$application->subject,
            $application->body,
            'Ref: SC-2026-000124',
            'Reply on the portal: '.route('dean.applications.show', $application).'?conversation='.$forward->conversation_id.'#correspondence',
        )
        ->and($messages[0])->not->toContain('Private note from the Admin Office.')
        ->and($raw)->toContain('Content-Disposition: attachment; name=fee-slip.pdf')
        ->and($raw)->not->toContain('Content-Disposition: inline')
        ->and($raw)->not->toContain('<img')
        ->and(preg_match('/^From: .*<'.preg_quote($hod->email, '/').'>/m', $messages[0]))->toBe(1);
    expect(Activity::query()->where('description', 'correspondence email sent')->where('subject_id', $forward->id)->exists())->toBeTrue();
});

test('a reply is "Re:" with the greeting, the generated signature and only the previous message of its own conversation quoted', function () {
    fakeGmailApi();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $dean = createDean($department->faculty);
    connectGmail($admin);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $application->update(['application_no' => 'SC-2026-000124', 'subject' => 'Fee instalment request']);

    $forward = recordForward($application, ForwardTarget::Hod, $admin, 'Please advise on the fee plan.', $hod);
    recordForward($application, ForwardTarget::Dean, $hod, 'Secret between HOD and Dean.', $dean);

    app(ReplyToCorrespondence::class)->handle($forward->conversation, $admin, 'Any update?', 'reply-key');

    $message = sentGmailMessages()[0];
    expect($message)->toContain(
        "Subject: Re: {$application->subject} (SC-2026-000124)",
        "Dear {$hod->name},",
        'Any update?',
        $admin->name,
        'Admin Officer',
        'Superior University',
        'wrote:',
        '> Please advise on the fee plan.',
        'Ref: SC-2026-000124',
    )
        ->and($message)->not->toContain('Secret between HOD and Dean.');
});

test('attachments beyond the size limit are listed by name instead of attached', function () {
    fakeGmailApi();
    Storage::fake('local');
    config(['google.forward_attachment_limit_bytes' => 100]);
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    connectGmail($admin);
    createHod($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    Storage::disk('local')->put('attachments/big-scan.pdf', str_repeat('x', 200));
    ApplicationAttachment::factory()->create([
        'application_id' => $application->id,
        'uploaded_by' => $student->id,
        'original_name' => 'big-scan.pdf',
        'file_path' => 'attachments/big-scan.pdf',
        'size' => 200,
    ]);

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward');

    expect(sentGmailMessages()[0])->toContain('Not attached (too large for one email): big-scan.pdf')
        ->and(sentGmailMessages(raw: true)[0])->not->toContain('Content-Disposition: attachment');
});

test('outside production MAIL_ALWAYS_TO catches forward emails too', function () {
    fakeGmailApi();
    config(['mail.always_to' => 'catch-all@superior.edu.pk']);
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    connectGmail($admin);
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward');

    expect(sentGmailMessages()[0])->toContain('To: catch-all@superior.edu.pk')
        ->not->toContain("To: {$hod->email}");
});

test('an expired access token is refreshed before sending', function () {
    fakeGmailApi();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $account = GoogleAccount::factory()->expired()->create(['user_id' => $admin->id, 'google_email' => $admin->email]);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward');

    Http::assertSent(fn (Request $request) => $request->url() === GmailSender::TOKEN_URL
        && $request['grant_type'] === 'refresh_token'
        && $request['refresh_token'] === $account->refresh_token);
    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://gmail.googleapis.com/upload/')
        && $request->header('Authorization') === ['Bearer ya29.refreshed']);
    expect($account->refresh()->access_token)->toBe('ya29.refreshed')
        ->and(CorrespondenceMessage::query()->sole()->email_status)->toBe(CorrespondenceEmailStatus::Sent);
});

test('a refused refresh token puts the email back to pending, flags the account for reconnecting and shows a banner', function () {
    Http::preventStrayRequests();
    Http::fake([GmailSender::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $account = GoogleAccount::factory()->expired()->create(['user_id' => $admin->id]);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward')
        ->assertHasNoErrors();

    $forward = CorrespondenceMessage::query()->sole();
    expect($forward->email_status)->toBe(CorrespondenceEmailStatus::AwaitingGmail)
        ->and($forward->email_error)->toBe('Gmail access was revoked or has expired. Reconnect your Gmail in Settings, then retry.')
        ->and($account->refresh()->needs_reconnect)->toBeTrue();

    $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertSee('Reconnect your Gmail.');
});

test('an email Gmail refuses is marked failed and the sender can retry it', function () {
    Http::preventStrayRequests();
    Http::fake([
        'gmail.googleapis.com/upload/*' => Http::sequence()
            ->push(['error' => ['message' => 'Invalid To header']], 400)
            ->push(['id' => 'gmail-message-2', 'threadId' => 'gmail-thread-2']),
    ]);
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    connectGmail($admin);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward');

    $forward = CorrespondenceMessage::query()->sole();
    expect($forward->email_status)->toBe(CorrespondenceEmailStatus::Failed)
        ->and($forward->email_error)->toBe('Gmail refused the email (HTTP 400): Invalid To header');
    expect(Activity::query()->where('description', 'correspondence email failed')->where('subject_id', $forward->id)->exists())->toBeTrue();

    Livewire::actingAs($admin)
        ->test(Thread::class, ['application' => $application])
        ->assertSee('Retry')
        ->call('retryEmail', $forward->id);

    expect($forward->refresh()->email_status)->toBe(CorrespondenceEmailStatus::Sent)
        ->and($forward->gmail_message_id)->toBe('gmail-message-2')
        ->and($forward->email_attempts)->toBe(2);
});

test('only the sender can retry a message\'s email', function () {
    Http::preventStrayRequests();
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $otherAdmin = createAdminOfficer($department);
    connectGmail($otherAdmin);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $forward = recordForward($application, ForwardTarget::Hod, $admin);
    $forward->update(['email_status' => CorrespondenceEmailStatus::Failed]);

    Livewire::actingAs($otherAdmin)
        ->test(Thread::class, ['application' => $application])
        ->call('retryEmail', $forward->id)
        ->assertForbidden();

    expect($forward->refresh()->email_status)->toBe(CorrespondenceEmailStatus::Failed);
    Http::assertNothingSent();
});
