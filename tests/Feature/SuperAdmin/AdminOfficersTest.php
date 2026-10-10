<?php

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Livewire\SuperAdmin\AdminOfficers\Index;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an admin officer and a student get a 403 from the admin officers page', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);

    Livewire::actingAs($admin)->test(Index::class)->assertForbidden();
    Livewire::actingAs($student)->test(Index::class)->assertForbidden();

    $this->actingAs($admin)->get(route('super-admin.admins.index'))->assertForbidden();
});

test('a super admin can create an admin officer who is active and verified immediately and can log in', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'Zeeshan Ahmed')
        ->set('email', 'zeeshan.ahmed@superior.edu.pk')
        ->set('department_id', (string) $department->id)
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('save')
        ->assertHasNoErrors();

    $admin = User::query()->where('email', 'zeeshan.ahmed@superior.edu.pk')->firstOrFail();

    expect($admin->status)->toBe(UserStatus::Active);
    expect($admin->email_verified_at)->not->toBeNull();
    expect($admin->hasRole(RoleName::AdminOfficer->value))->toBeTrue();

    $this->post(route('login.store'), [
        'email' => $admin->email,
        'password' => 'Password123!',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
});

test('the admin officer email must use the university domain', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('name', 'Zeeshan Ahmed')
        ->set('email', 'zeeshan.ahmed@gmail.com')
        ->set('department_id', (string) $department->id)
        ->set('password', 'Password123!')
        ->set('password_confirmation', 'Password123!')
        ->call('save')
        ->assertHasErrors(['email']);
});

test('an admin officer can be edited, suspended and reactivated, with no delete ability', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $admin = createAdminOfficer($department);

    $component = Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->call('startEdit', $admin->id)
        ->set('name', 'Updated Name')
        ->set('email', 'updated.officer@superior.edu.pk')
        ->call('save')
        ->assertHasNoErrors();

    expect($admin->refresh()->name)->toBe('Updated Name');

    $component->call('suspend', $admin->id)->assertHasNoErrors();
    expect($admin->refresh()->status)->toBe(UserStatus::Suspended);

    $component->call('reactivate', $admin->id)->assertHasNoErrors();
    expect($admin->refresh()->status)->toBe(UserStatus::Active);
});
