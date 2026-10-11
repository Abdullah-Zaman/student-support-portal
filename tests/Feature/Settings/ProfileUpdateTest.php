<?php

use App\Livewire\Settings\Profile;
use App\Models\User;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('a student is redirected away from the shared settings profile page', function () {
    createRoles();
    $student = createStudentUser();

    $this->actingAs($student)->get('/settings/profile')
        ->assertRedirect(route('student.profile'));
});

test('a student cannot change their name or email through the shared settings profile page', function () {
    createRoles();
    $student = createStudentUser();
    $originalName = $student->name;
    $originalEmail = $student->email;

    $this->actingAs($student);

    $component = new Profile;
    $component->name = 'Someone Else';
    $component->email = 'someone-else@superior.edu.pk';

    expect(fn () => $component->updateProfileInformation())
        ->toThrow(HttpException::class);

    expect($student->refresh()->name)->toBe($originalName);
    expect($student->refresh()->email)->toBe($originalEmail);
});

test('profile page is displayed', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/settings/profile')->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toEqual('Test User');
    expect($user->email)->toEqual('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when email address is unchanged', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    $response = Livewire::test(Profile::class)
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation');

    $response->assertHasNoErrors();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});
