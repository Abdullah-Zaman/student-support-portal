<?php

use App\Enums\UserStatus;
use App\Livewire\Admin\Students\Index;
use App\Models\Department;
use App\Models\Program;
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

test('students can be filtered by roll number, batch and program', function (string $filter, string $value) {
    $department = Department::factory()->create();
    $bsse = Program::factory()->create(['department_id' => $department->id, 'code' => 'BSSE']);
    $bsds = Program::factory()->create(['department_id' => $department->id, 'code' => 'BSDS']);
    $admin = createAdminOfficer($department);

    $matching = createStudentUser($department);
    $matching->update(['name' => 'Ayesha Khan']);
    $matching->student->update(['registration_no' => 'SU92-BSSEM-F22-171', 'batch' => 'F22', 'program_id' => $bsse->id]);

    $other = createStudentUser($department);
    $other->update(['name' => 'Bilal Yousaf']);
    $other->student->update(['registration_no' => 'SU92-BSDSM-F23-045', 'batch' => 'F23', 'program_id' => $bsds->id]);

    $value = $filter === 'program_id' ? (string) $bsse->id : $value;

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set($filter, $value)
        ->assertSee('Ayesha Khan')
        ->assertDontSee('Bilal Yousaf');
})->with([
    'partial roll number' => ['roll_no', 'f22-171'],
    'batch' => ['batch', 'F22'],
    'program' => ['program_id', ''],
]);

test('the batch filter offers each batch present in the department, newest first', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    foreach (['F22', 'F23', 'S23'] as $batch) {
        createStudentUser($department)->student->update(['batch' => $batch]);
    }
    createStudentUser()->student->update(['batch' => 'F19']);

    $component = Livewire::actingAs($admin)->test(Index::class);

    expect($component->instance()->batches())->toBe([
        'F23' => 'Fall 2023',
        'S23' => 'Spring 2023',
        'F22' => 'Fall 2022',
    ]);
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
        ->set('roll_no', 'F22-171')
        ->set('batch', 'F22')
        ->set('program_id', '1')
        ->set('semester', '3')
        ->set('status', 'suspended');

    expect($component->get('hasActiveFilters'))->toBeTrue();

    $component->call('clearFilters');

    expect($component->get('hasActiveFilters'))->toBeFalse();
    expect($component->get('search'))->toBe('');
    expect($component->get('roll_no'))->toBe('');
    expect($component->get('batch'))->toBe('');
    expect($component->get('program_id'))->toBe('');
    expect($component->get('semester'))->toBe('');
    expect($component->get('status'))->toBe('');
});
