<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function () {
    $response = $this->get(route('password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function () {
    Notification::fake();

    $user = User::factory()->create();

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $response = $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('status', 'Your password has been reset. Please log in with your new password.');

        return true;
    });
});

test('password reset requests are rate limited to 5 attempts per minute per email and ip', function () {
    $user = User::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('password.request'), ['email' => $user->email]);
    }

    $response = $this->post(route('password.request'), ['email' => $user->email]);

    $response->assertStatus(429);
});

test('the forgot password form shows the same generic message whether the email is registered or not', function () {
    $user = User::factory()->create();

    $registered = $this->post(route('password.request'), ['email' => $user->email]);
    $unregistered = $this->post(route('password.request'), ['email' => 'nobody@superior.edu.pk']);

    $message = 'If this email is registered, a password reset link has been sent.';

    $registered->assertSessionHasNoErrors()->assertSessionHas('status', $message);
    $unregistered->assertSessionHasNoErrors()->assertSessionHas('status', $message);
});

test('resetting a password logs out the user\'s other sessions', function () {
    Notification::fake();

    $user = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'other-session-id',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'test',
        'payload' => base64_encode('payload'),
        'last_activity' => now()->timestamp,
    ]);

    $this->post(route('password.request'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        return true;
    });

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();
});
