<?php

use App\Models\GoogleAccount;
use App\Models\User;
use Laravel\Socialite\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    createRoles();
});

/**
 * Give a user a university email address, as staff sign-in requires.
 */
function withUniversityEmail(User $user): User
{
    $user->update(['email' => "user{$user->id}@superior.edu.pk"]);

    return $user;
}

function fakeGoogleSignIn(string $email): void
{
    Socialite::fake('google', GoogleUser::fake(['id' => 'google-456', 'name' => 'Google Name', 'email' => $email]));
}

test('a staff member signs in with the Google account of their portal email', function () {
    $admin = withUniversityEmail(createAdminOfficer());
    fakeGoogleSignIn($admin->email);
    $this->withSession(['google_oauth_intent' => 'login']);
    $sessionIdBefore = session()->getId();

    $this->get(route('google.callback'))->assertRedirect(route('dashboard'));

    $this->assertAuthenticatedAs($admin);
    expect(session()->getId())->not->toBe($sessionIdBefore)
        ->and($admin->refresh()->last_login_at)->not->toBeNull();
});

test('google sign-in is refused, and never creates a user, for', function (Closure $makeEmail, string $error) {
    $email = $makeEmail();
    $usersBefore = User::query()->count();
    fakeGoogleSignIn($email);

    $this->withSession(['google_oauth_intent' => 'login'])
        ->get(route('google.callback'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => $error]);

    $this->assertGuest();
    expect(User::query()->count())->toBe($usersBefore);
})->with([
    'an unregistered university account' => [fn () => 'new.person@superior.edu.pk', 'Your account is not registered in the portal.'],
    'a student' => [fn () => withUniversityEmail(createStudentUser())->email, 'Google sign-in is for staff only. Students log in with their university email and password.'],
    'a suspended staff member' => [fn () => tap(withUniversityEmail(createHod()))->update(['status' => 'suspended'])->email, 'Your account has been suspended. Contact the SE Department Admin Office.'],
    'an account outside the staff domains' => [fn () => 'someone@gmail.com', 'Use your university Google account (@superior.edu.pk).'],
]);

test('a staff member is matched by their connected Google email only when mismatch is allowed', function (bool $allowMismatch) {
    config(['google.allow_email_mismatch' => $allowMismatch]);
    $dean = createDean();
    GoogleAccount::factory()->create(['user_id' => $dean->id, 'google_email' => 'dean.personal@gmail.com']);
    fakeGoogleSignIn('dean.personal@gmail.com');

    $this->withSession(['google_oauth_intent' => 'login'])->get(route('google.callback'));

    $allowMismatch ? $this->assertAuthenticatedAs($dean) : $this->assertGuest();
})->with(['mismatch allowed' => true, 'mismatch not allowed' => false]);

test('with staff password login off, only the Super Admin and students may use a password', function (Closure $makeUser, bool $allowed) {
    config(['google.staff_password_login' => false]);
    $user = $makeUser();

    $response = $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    if ($allowed) {
        $this->assertAuthenticatedAs($user);
    } else {
        $response->assertSessionHasErrors(['email' => 'Staff sign in with Google. Use the "Sign in with Google" button.']);
        $this->assertGuest();
    }
})->with([
    'admin officer' => [fn () => createAdminOfficer(), false],
    'HOD' => [fn () => createHod(), false],
    'Dean' => [fn () => createDean(), false],
    'super admin' => [fn () => createSuperAdmin(), true],
    'student' => [fn () => createStudentUser(), true],
]);

test('staff keep password login while staff password login is on', function () {
    config(['google.staff_password_login' => true]);
    $admin = createAdminOfficer();

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password']);

    $this->assertAuthenticatedAs($admin);
});
