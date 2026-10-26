<?php

use App\Livewire\Settings\ConnectedGmail;
use App\Models\GoogleAccount;
use App\Support\Gmail\GmailSender;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

/**
 * Google's callback for a connect, as the given Google account.
 *
 * @param  array<int, string>|null  $scopes
 */
function fakeGoogleConnectCallback(string $email, ?array $scopes = null): void
{
    Socialite::fake('google', GoogleUser::fake([
        'id' => 'google-123',
        'name' => 'Staff Member',
        'email' => $email,
        'token' => 'access-token-abc',
        'refreshToken' => 'refresh-token-xyz',
        'expiresIn' => 3599,
        'approvedScopes' => $scopes ?? ['openid', 'email', 'profile', GoogleAccount::GMAIL_SEND_SCOPE],
    ]));
}

test('connecting sends the staff member to Google for gmail.send with offline access and a consent prompt', function () {
    config([
        'services.google.client_id' => 'client-id',
        'services.google.client_secret' => 'client-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
    ]);
    $admin = createAdminOfficer();

    $location = $this->actingAs($admin)->get(route('google.connect'))->assertRedirect()->headers->get('Location');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
    expect($location)->toStartWith('https://accounts.google.com/')
        ->and(explode(' ', $query['scope']))->toContain(GoogleAccount::GMAIL_SEND_SCOPE, 'openid', 'email', 'profile')
        ->and($query['access_type'])->toBe('offline')
        ->and($query['prompt'])->toBe('consent');
});

test('a student cannot reach the Gmail connect route, callback or card', function () {
    $student = createStudentUser();

    $this->actingAs($student)->get(route('google.connect'))->assertForbidden();

    fakeGoogleConnectCallback($student->email);
    $this->actingAs($student)
        ->withSession(['google_oauth_intent' => 'connect'])
        ->get(route('google.callback'))
        ->assertForbidden();

    Livewire::actingAs($student)->test(ConnectedGmail::class)->assertForbidden();

    expect(GoogleAccount::query()->count())->toBe(0);
});

test('connecting Gmail stores the tokens encrypted and logs it', function () {
    $admin = createAdminOfficer();
    fakeGoogleConnectCallback($admin->email);

    $this->actingAs($admin)
        ->withSession(['google_oauth_intent' => 'connect'])
        ->get(route('google.callback'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('gmail_status');

    $row = DB::table('google_accounts')->where('user_id', $admin->id)->sole();
    expect($row->google_email)->toBe($admin->email)
        ->and($row->refresh_token)->not->toContain('refresh-token-xyz')
        ->and($row->access_token)->not->toContain('access-token-abc')
        ->and(Crypt::decryptString($row->refresh_token))->toBe('refresh-token-xyz')
        ->and(Crypt::decryptString($row->access_token))->toBe('access-token-abc');

    expect(Activity::query()->where('description', 'gmail connected')->where('causer_id', $admin->id)->exists())->toBeTrue();
});

test('a Google account that differs from the portal email is refused unless mismatch is allowed outside production', function (bool $allowMismatch, string $environment, bool $connected) {
    config(['google.allow_email_mismatch' => $allowMismatch]);
    app()->detectEnvironment(fn () => $environment);
    $admin = createAdminOfficer();
    fakeGoogleConnectCallback('someone.personal@gmail.com');

    $response = $this->actingAs($admin)
        ->withSession(['google_oauth_intent' => 'connect'])
        ->get(route('google.callback'))
        ->assertRedirect(route('profile.edit'));

    expect($admin->googleAccount()->exists())->toBe($connected);

    if ($connected) {
        $response->assertSessionHas('gmail_status');
    } else {
        $response->assertSessionHas('gmail_error', "Connect the Google account for your portal email ({$admin->email}).");
    }
})->with([
    'mismatch not allowed' => [false, 'local', false],
    'mismatch allowed locally' => [true, 'local', true],
    'mismatch allowed but production' => [true, 'production', false],
]);

test('a connection without the gmail.send permission is refused', function () {
    $admin = createAdminOfficer();
    fakeGoogleConnectCallback($admin->email, ['openid', 'email', 'profile']);

    $this->actingAs($admin)
        ->withSession(['google_oauth_intent' => 'connect'])
        ->get(route('google.callback'))
        ->assertSessionHas('gmail_error');

    expect($admin->googleAccount()->exists())->toBeFalse();
});

test('disconnecting revokes the token at Google and deletes it', function () {
    fakeGmailApi();
    $hod = createHod();
    $account = connectGmail($hod);

    Livewire::actingAs($hod)
        ->test(ConnectedGmail::class)
        ->assertSee($hod->email)
        ->call('disconnect');

    Http::assertSent(fn (Request $request) => $request->url() === GmailSender::REVOKE_URL
        && $request['token'] === $account->refresh_token);
    expect(GoogleAccount::query()->count())->toBe(0)
        ->and(Activity::query()->where('description', 'gmail disconnected')->where('causer_id', $hod->id)->exists())->toBeTrue();
});

test('a test email is sent from the connected Gmail to its own address', function () {
    fakeGmailApi();
    $dean = createDean();
    connectGmail($dean);

    Livewire::actingAs($dean)->test(ConnectedGmail::class)->call('sendTestEmail');

    Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://gmail.googleapis.com/upload/')
        && $request->hasHeader('Authorization')
        && str_contains($request->body(), "To: {$dean->email}")
        && preg_match('/^From: .*<'.preg_quote($dean->email, '/').'>/m', $request->body()) === 1);
    expect(Activity::query()->where('description', 'gmail test email sent')->where('causer_id', $dean->id)->exists())->toBeTrue();
});
