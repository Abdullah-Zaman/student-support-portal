<?php

use App\Models\User;
use Spatie\Activitylog\Models\Activity;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});

test('a user with an unverified email can still log in', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertAuthenticated();
});

test('a suspended user cannot log in and sees the suspension message', function () {
    $user = User::factory()->suspended()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors([
        'email' => 'Your account has been suspended. Contact the SE Department Admin Office.',
    ]);

    $this->assertGuest();
});

test('a successful login updates last_login_at and writes an activity log entry', function () {
    $user = User::factory()->create(['last_login_at' => null]);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($user->refresh()->last_login_at)->not->toBeNull();

    expect(Activity::query()
        ->where('causer_id', $user->id)
        ->where('description', 'login')
        ->exists())->toBeTrue();
});

test('a failed login with a wrong password is recorded in the activity log, without the password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $activity = Activity::query()->where('description', 'login_failed')->latest('id')->first();

    // Fortify's Failed event never carries a resolved user (even for a
    // wrong password against a real account), so there is no causer here.
    expect($activity)->not->toBeNull();
    expect($activity->properties->get('email'))->toBe($user->email);
    expect($activity->properties->has('password'))->toBeFalse();
});

test('a failed login with an unknown email is still recorded, without a causer', function () {
    $this->post(route('login.store'), [
        'email' => 'nobody@superior.edu.pk',
        'password' => 'whatever',
    ]);

    $activity = Activity::query()->where('description', 'login_failed')->latest('id')->first();

    expect($activity)->not->toBeNull();
    expect($activity->causer_id)->toBeNull();
    expect($activity->properties->get('email'))->toBe('nobody@superior.edu.pk');
});
