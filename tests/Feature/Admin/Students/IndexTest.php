<?php

use App\Enums\UserStatus;
use App\Livewire\Admin\Students\Index;
use App\Models\Department;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an admin officer only sees students from their own department', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $admin = createAdminOfficer($departmentA);

    $ownStudent = createStudentUser($departmentA);
    $ownStudent->update(['name' => 'Ayesha Khan']);

    $otherStudent = createStudentUser($departmentB);
    $otherStudent->update(['name' => 'Bilal Yousaf']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Ayesha Khan')
        ->assertDontSee('Bilal Yousaf');
});

test('search matches by name, registration number or email', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $student->update(['name' => 'Fatima Noor']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'Fatima')
        ->assertSee($student->student->registration_no);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', $student->student->registration_no)
        ->assertSee('Fatima Noor');
});

test('status filter narrows the results', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    $active = createStudentUser($department);
    $active->update(['name' => 'Active Student']);

    $suspended = createStudentUser($department);
    $suspended->update(['name' => 'Suspended Student', 'status' => UserStatus::Suspended]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('status', 'suspended')
        ->assertSee('Suspended Student')
        ->assertDontSee('Active Student')
        ->set('status', '')
        ->assertSee('Active Student')
        ->assertSee('Suspended Student');
});

test('a super admin sees students from every department and can filter to one', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $superAdmin = createSuperAdmin();

    $studentA = createStudentUser($departmentA);
    $studentA->update(['name' => 'Ayesha Khan']);

    $studentB = createStudentUser($departmentB);
    $studentB->update(['name' => 'Bilal Yousaf']);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->assertSee('Ayesha Khan')
        ->assertSee('Bilal Yousaf')
        ->set('department_id', (string) $departmentA->id)
        ->assertSee('Ayesha Khan')
        ->assertDontSee('Bilal Yousaf');
});

test('clearFilters resets search and every dropdown', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    $component = Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'something')
        ->set('program', 'BS Software Engineering')
        ->set('semester', '3')
        ->set('status', 'suspended');

    expect($component->get('hasActiveFilters'))->toBeTrue();

    $component->call('clearFilters');

    expect($component->get('search'))->toBe('');
    expect($component->get('program'))->toBe('');
    expect($component->get('semester'))->toBe('');
    expect($component->get('status'))->toBe('');
});
