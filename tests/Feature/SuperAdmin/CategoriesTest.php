<?php

use App\Livewire\Student\Applications\Create;
use App\Livewire\SuperAdmin\Categories\Index;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\Department;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an admin officer and a student get a 403 from the categories page', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);

    Livewire::actingAs($admin)->test(Index::class)->assertForbidden();
    Livewire::actingAs($student)->test(Index::class)->assertForbidden();
});

test('a super admin can create, edit and deactivate a category for a chosen department', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('department_id', (string) $department->id)
        ->call('startCreate')
        ->set('name', 'Hostel Issue')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('Hostel Issue');

    $category = ApplicationCategory::query()->where('name', 'Hostel Issue')->firstOrFail();
    expect($category->department_id)->toBe($department->id);
    expect($category->is_active)->toBeTrue();

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('department_id', (string) $department->id)
        ->call('toggleActive', $category->id)
        ->assertHasNoErrors();

    expect($category->refresh()->is_active)->toBeFalse();
});

test('category names must be unique within the same department', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    ApplicationCategory::factory()->create(['department_id' => $department->id, 'name' => 'Fee Issue']);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('department_id', (string) $department->id)
        ->call('startCreate')
        ->set('name', 'Fee Issue')
        ->call('save')
        ->assertHasErrors(['name' => 'unique']);
});

test('the same category name is allowed in a different department', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    ApplicationCategory::factory()->create(['department_id' => $departmentA->id, 'name' => 'Fee Issue']);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('department_id', (string) $departmentB->id)
        ->call('startCreate')
        ->set('name', 'Fee Issue')
        ->call('save')
        ->assertHasNoErrors();
});

test('deactivating a category keeps existing applications untouched and hides it from the student form', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $student = createStudentUser($department);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id, 'is_active' => true]);
    $application = Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
    ]);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('department_id', (string) $department->id)
        ->call('toggleActive', $category->id)
        ->assertHasNoErrors();

    expect($application->refresh()->category_id)->toBe($category->id);

    Livewire::actingAs($student)
        ->test(Create::class)
        ->assertDontSee($category->name);
});

test('categories can be reordered', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $first = ApplicationCategory::factory()->create(['department_id' => $department->id, 'sort_order' => 1]);
    $second = ApplicationCategory::factory()->create(['department_id' => $department->id, 'sort_order' => 2]);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->set('department_id', (string) $department->id)
        ->call('moveDown', $first->id)
        ->assertHasNoErrors();

    expect($first->refresh()->sort_order)->toBe(2);
    expect($second->refresh()->sort_order)->toBe(1);
});
