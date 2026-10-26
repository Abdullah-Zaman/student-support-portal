<?php

use App\Livewire\Auth\Register;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
    seedAcademicStructure();
});

test('registration number is uppercased as it is typed', function () {
    Livewire::test(Register::class)
        ->set('registration_no', 'su92-bssem-f22-171')
        ->assertSet('registration_no', 'SU92-BSSEM-F22-171');
});

test('typing an invalid value does not show a new error until blur', function () {
    Livewire::test(Register::class)
        ->set('registration_no', 'not-a-valid-format')
        ->assertHasNoErrors()
        ->call('blurred', 'registration_no')
        ->assertHasErrors(['registration_no']);
});

test('an existing error clears live as soon as the value becomes valid, without needing another blur', function () {
    Livewire::test(Register::class)
        ->set('registration_no', 'not-a-valid-format')
        ->call('blurred', 'registration_no')
        ->assertHasErrors(['registration_no'])
        ->set('registration_no', 'SU92-BSSEM-F22-171')
        ->assertHasNoErrors(['registration_no']);
});

test('registration number must match the selected program, checked on blur', function () {
    Livewire::test(Register::class)
        ->set('program', 'BSDS')
        ->set('registration_no', 'SU92-BSSEM-F22-171')
        ->assertHasNoErrors()
        ->call('blurred', 'registration_no')
        ->assertHasErrors(['registration_no']);
});

test('changing the program immediately rechecks an already-filled registration number', function () {
    Livewire::test(Register::class)
        ->set('program', 'BSSE')
        ->set('registration_no', 'SU92-BSSEM-F22-171')
        ->call('blurred', 'registration_no')
        ->assertHasNoErrors(['registration_no'])
        ->set('program', 'BSDS')
        ->assertHasErrors(['registration_no']);
});

test('email must match the registration number, checked on blur', function () {
    Livewire::test(Register::class)
        ->set('registration_no', 'SU92-BSSEM-F22-171')
        ->set('email', 'someone-else@superior.edu.pk')
        ->assertHasNoErrors()
        ->call('blurred', 'email')
        ->assertHasErrors(['email'])
        ->set('email', 'su92-bssem-f22-171@superior.edu.pk')
        ->assertHasNoErrors(['email']);
});

test('changing the registration number immediately rechecks an already-filled email', function () {
    Livewire::test(Register::class)
        ->set('registration_no', 'SU92-BSSEM-F22-171')
        ->set('email', 'su92-bssem-f22-171@superior.edu.pk')
        ->call('blurred', 'email')
        ->assertHasNoErrors(['email'])
        ->set('registration_no', 'SU92-BSSEM-F22-999')
        ->assertHasErrors(['email']);
});

test('changing the password immediately rechecks an already-filled confirmation', function () {
    Livewire::test(Register::class)
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('blurred', 'password_confirmation')
        ->assertHasNoErrors(['password'])
        ->set('password', 'different-password')
        ->assertHasErrors(['password']);
});

test('the full registration form submits successfully', function () {
    Livewire::test(Register::class)
        ->set('name', 'Ayesha Khan')
        ->set('program', 'BSSE')
        ->set('registration_no', 'SU92-BSSEM-F22-171')
        ->set('email', 'su92-bssem-f22-171@superior.edu.pk')
        ->set('current_semester', '5')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});
