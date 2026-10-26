<?php

use App\Enums\ForwardEmailStatus;
use App\Enums\ForwardTarget;
use App\Livewire\Admin\Applications\Show as AdminShow;
use App\Livewire\Reviewer\Applications\Show as ReviewerShow;
use App\Models\ApplicationAttachment;
use App\Models\ApplicationForward;
use App\Models\Department;
use App\Models\GoogleAccount;
use App\Support\Gmail\GmailSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

test('a forward is blocked until the forwarder connects their Gmail', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->assertSee('Connect your Gmail first')
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward')
        ->assertHasErrors(['forward_target']);

    expect(ApplicationForward::query()->count())->toBe(0);
});

test('a forward is blocked while the forwarder\'s Gmail needs reconnecting', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    GoogleAccount::factory()->needsReconnect()->create(['user_id' => $admin->id]);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->assertSee('Reconnect your Gmail first')
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward')
        ->assertHasErrors(['forward_target']);

    expect(ApplicationForward::query()->count())->toBe(0);
});

test('a forward is emailed from the forwarder\'s own Gmail with the application, student, remark chain and attachments', function () {
    fakeGmailApi();
    Storage::fake('local');
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    connectGmail($hod);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['application_no' => 'SC-2026-000124']);
    Storage::disk('local')->put('attachments/fee-slip.pdf', '%PDF-1.4 fee slip');
    ApplicationAttachment::factory()->create([
        'application_id' => $application->id,
        'uploaded_by' => $student->id,
        'original_name' => 'fee-slip.pdf',
        'file_path' => 'attachments/fee-slip.pdf',
        'size' => 17,
    ]);
    recordForward($application, ForwardTarget::Hod, $admin, 'Earlier note from the Admin Office.');
    $dean = createDean($department->faculty);

    Livewire::actingAs($hod)
        ->test(ReviewerShow::class, ['application' => $application])
        ->set('forward_target', 'dean')
        ->set('forward_note', 'Recommended for a fee waiver.')
        ->call('forward')
        ->assertHasNoErrors();

    $forward = ApplicationForward::query()->where('to_role', ForwardTarget::Dean)->sole();
    expect($forward->email_status)->toBe(ForwardEmailStatus::Sent)
        ->and($forward->gmail_message_id)->toBe('gmail-message-1')
        ->and($forward->emailed_at)->not->toBeNull();

    $messages = sentGmailMessages();
    expect($messages)->toHaveCount(1)
        ->and($messages[0])->toContain(
            "To: {$dean->email}",
            $application->application_no,
            'Recommended for a fee waiver.',
            'Earlier note from the Admin Office.',
            $student->student->registration_no,
        )
        ->and(sentGmailMessages(raw: true)[0])->toContain(
            'Content-Disposition: attachment; name=fee-slip.pdf',
            'Content-Disposition: inline; name=superior-logo',
        )
        ->and(preg_match('/^From: .*<'.preg_quote($hod->email, '/').'>/m', $messages[0]))->toBe(1);
    expect(Activity::query()->where('description', 'forward email sent')->where('subject_id', $forward->id)->exists())->toBeTrue();
});

test('attachments beyond the size limit are listed by name with a portal link instead of attached', function () {
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

    expect(sentGmailMessages()[0])->toContain('Not attached (too large for one email): big-scan.pdf', route('hod.applications.show', $application))
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
        ->and(ApplicationForward::query()->sole()->email_status)->toBe(ForwardEmailStatus::Sent);
});

test('a refused refresh token fails the email, flags the account for reconnecting and shows a banner', function () {
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

    $forward = ApplicationForward::query()->sole();
    expect($forward->email_status)->toBe(ForwardEmailStatus::Failed)
        ->and($forward->email_error)->toBe('Gmail access was revoked or has expired. Reconnect your Gmail in Settings, then retry.')
        ->and($account->refresh()->needs_reconnect)->toBeTrue();
    expect(Activity::query()->where('description', 'forward email failed')->where('subject_id', $forward->id)->exists())->toBeTrue();

    $this->actingAs($admin->fresh())->get(route('admin.dashboard'))->assertSee('Reconnect your Gmail.');
});

test('a forward email Gmail refuses is marked failed and the forwarder can retry it', function () {
    Http::preventStrayRequests();
    Http::fake([
        'gmail.googleapis.com/upload/*' => Http::sequence()
            ->push(['error' => ['message' => 'Invalid To header']], 400)
            ->push(['id' => 'gmail-message-2']),
    ]);
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    connectGmail($admin);
    createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    $component = Livewire::actingAs($admin)
        ->test(AdminShow::class, ['application' => $application])
        ->set('forward_target', 'hod')
        ->set('forward_note', 'Please review.')
        ->call('forward');

    $forward = ApplicationForward::query()->sole();
    expect($forward->email_status)->toBe(ForwardEmailStatus::Failed)
        ->and($forward->email_error)->toBe('Gmail refused the email (HTTP 400): Invalid To header');

    $component->assertSee('Retry email')->call('sendForwardEmail', $forward->id);

    expect($forward->refresh()->email_status)->toBe(ForwardEmailStatus::Sent)
        ->and($forward->gmail_message_id)->toBe('gmail-message-2')
        ->and($forward->email_attempts)->toBe(2);
});

test('only the forwarder can send a forward\'s email', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $otherAdmin = createAdminOfficer($department);
    connectGmail($otherAdmin);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $forward = recordForward($application, ForwardTarget::Registrar, $admin);

    Livewire::actingAs($otherAdmin)
        ->test(AdminShow::class, ['application' => $application])
        ->call('sendForwardEmail', $forward->id)
        ->assertForbidden();
});

test('a Registrar forward still pending email is sent once the forwarder connects Gmail', function () {
    fakeGmailApi();
    $department = Department::factory()->create();
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, createAdminOfficer($department));
    $forward = recordForward($application, ForwardTarget::Registrar, $hod);
    $forward->update(['to_email' => 'registrar@superior.edu.pk']);

    Livewire::actingAs($hod)
        ->test(ReviewerShow::class, ['application' => $application])
        ->call('sendForwardEmail', $forward->id);

    expect($forward->refresh()->email_status)->toBe(ForwardEmailStatus::PendingEmail);

    connectGmail($hod);

    Livewire::actingAs($hod)
        ->test(ReviewerShow::class, ['application' => $application])
        ->assertSee('Send email')
        ->call('sendForwardEmail', $forward->id);

    expect($forward->refresh()->email_status)->toBe(ForwardEmailStatus::Sent)
        ->and(sentGmailMessages()[0])->toContain('To: registrar@superior.edu.pk');
});
