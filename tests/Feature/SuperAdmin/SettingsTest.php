<?php

use App\Livewire\Student\Applications\Create;
use App\Livewire\SuperAdmin\Settings\Edit;
use App\Models\ApplicationCategory;
use App\Models\Department;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an admin officer and a student get a 403 from the settings page', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);

    Livewire::actingAs($admin)->test(Edit::class)->assertForbidden();
    Livewire::actingAs($student)->test(Edit::class)->assertForbidden();
});

test('the max-length values must stay within their allowed ranges', function () {
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(Edit::class)
        ->set('subject_max_length', 10)
        ->set('body_max_length', 100)
        ->call('save')
        ->assertHasErrors(['subject_max_length' => 'between', 'body_max_length' => 'between']);
});

test('saving a new body max length takes effect immediately in the student application form', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();
    $student = createStudentUser($department);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);

    Livewire::actingAs($superAdmin)
        ->test(Edit::class)
        ->set('subject_max_length', 150)
        ->set('body_max_length', 1000)
        ->call('save')
        ->assertHasNoErrors();

    // 120 and 800 characters exceed the old defaults (100 / 500) but fit
    // the new limits just saved above, proving the cache was cleared.
    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', str_repeat('a', 120))
        ->set('body', str_repeat('b', 800))
        ->call('submit')
        ->assertHasNoErrors();
});
