<?php

use App\Actions\Forwarding\ForwardApplication;
use App\Livewire\SuperAdmin\Reviewers\Index;
use App\Livewire\SuperAdmin\Settings\Edit;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('a super admin creates an HOD for a department', function () {
    $department = Department::factory()->create();

    Livewire::actingAs(createSuperAdmin())
        ->test(Index::class)
        ->call('startCreate')
        ->set('role', 'hod')
        ->set('name', 'Dr. Hamza')
        ->set('email', 'hod.it@superior.edu.pk')
        ->set('department_id', (string) $department->id)
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('save')
        ->assertHasNoErrors();

    $hod = User::query()->where('email', 'hod.it@superior.edu.pk')->sole();
    expect($hod->hasRole('hod'))->toBeTrue()
        ->and($hod->department_id)->toBe($department->id)
        ->and($hod->faculty_id)->toBeNull();
});

test('a super admin creates a Dean for a faculty', function () {
    $faculty = Faculty::factory()->create();

    Livewire::actingAs(createSuperAdmin())
        ->test(Index::class)
        ->call('startCreate')
        ->set('role', 'dean')
        ->set('name', 'Dr. Dean')
        ->set('email', 'dean.foc@superior.edu.pk')
        ->set('faculty_id', (string) $faculty->id)
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('save')
        ->assertHasNoErrors();

    $dean = User::query()->where('email', 'dean.foc@superior.edu.pk')->sole();
    expect($dean->hasRole('dean'))->toBeTrue()
        ->and($dean->faculty_id)->toBe($faculty->id)
        ->and($dean->department_id)->toBeNull();
});

test('a department cannot get a second active HOD', function () {
    $department = Department::factory()->create();
    createHod($department);

    Livewire::actingAs(createSuperAdmin())
        ->test(Index::class)
        ->call('startCreate')
        ->set('role', 'hod')
        ->set('name', 'Second HOD')
        ->set('email', 'second.hod@superior.edu.pk')
        ->set('department_id', (string) $department->id)
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('save')
        ->assertHasErrors(['department_id']);
});

test('a suspended HOD cannot be reactivated while another HOD is active', function () {
    $department = Department::factory()->create();
    $oldHod = createHod($department);
    $oldHod->update(['status' => 'suspended']);
    createHod($department);

    Livewire::actingAs(createSuperAdmin())
        ->test(Index::class)
        ->call('reactivate', $oldHod->id);

    expect($oldHod->refresh()->status->value)->toBe('suspended');
});

test('an admin officer cannot manage HODs and Deans', function () {
    Livewire::actingAs(createAdminOfficer())
        ->test(Index::class)
        ->assertForbidden();
});

test('a super admin sets the Registrar email', function () {
    Livewire::actingAs(createSuperAdmin())
        ->test(Edit::class)
        ->set('registrar_email', 'Registrar@Superior.edu.pk')
        ->call('save')
        ->assertHasNoErrors();

    expect(ForwardApplication::registrarEmail())->toBe('registrar@superior.edu.pk');
});
