<?php

use App\Livewire\SuperAdmin\AuditLog\Index;
use App\Models\Department;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an admin officer and a student get a 403 from the audit log', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);

    Livewire::actingAs($admin)->test(Index::class)->assertForbidden();
    Livewire::actingAs($student)->test(Index::class)->assertForbidden();
});

test('a super admin sees a logged name change and a login in the audit log', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $admin = createAdminOfficer($department);

    activity()->causedBy($admin)->log('login');
    $admin->update(['name' => 'Renamed Officer']);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->assertSee('Renamed Officer')
        ->assertSee('Login');
});

test('the audit log never renders a password or remember_token field', function () {
    $superAdmin = createSuperAdmin();
    $other = User::factory()->create();

    activity()
        ->causedBy($superAdmin)
        ->performedOn($other)
        ->withChanges([
            'attributes' => ['name' => 'New Name', 'password' => 'should-not-leak', 'remember_token' => 'should-not-leak-either'],
            'old' => ['name' => 'Old Name', 'password' => 'old-hash', 'remember_token' => 'old-token'],
        ])
        ->log('updated');

    $response = Livewire::actingAs($superAdmin)->test(Index::class);

    $response->assertDontSee('should-not-leak')
        ->assertDontSee('should-not-leak-either')
        ->assertDontSee('old-hash')
        ->assertDontSee('old-token');
});

test('the audit log can be filtered by user and date range', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $admin = createAdminOfficer($department);
    $otherAdmin = createAdminOfficer($department);

    $this->actingAs($superAdmin);
    $admin->update(['name' => 'Admin One Renamed']);
    $otherAdmin->update(['name' => 'Admin Two Renamed']);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('causer_id', (string) $superAdmin->id)
        ->assertSee('Admin One Renamed')
        ->assertSee('Admin Two Renamed');

    // A future date_from excludes every activity that just happened, even
    // though both names still appear in the (unfiltered) "User" dropdown.
    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('date_from', now()->addDay()->toDateString())
        ->assertSee('No activity found');
});
