<?php

use App\Livewire\Admin\Applications\Index as ApplicationsIndex;
use App\Livewire\SuperAdmin\Departments\Index as DepartmentsIndex;
use App\Livewire\SuperAdmin\Faculties\Index as FacultiesIndex;
use App\Livewire\SuperAdmin\Programs\Index as ProgramsIndex;
use App\Models\ApplicationCategory;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

test('an admin officer and a student get a 403 from the faculty, department and program pages', function (string $component) {
    $department = Department::factory()->create();

    Livewire::actingAs(createAdminOfficer($department))->test($component)->assertForbidden();
    Livewire::actingAs(createStudentUser($department))->test($component)->assertForbidden();
})->with([
    'faculties' => FacultiesIndex::class,
    'departments' => DepartmentsIndex::class,
    'programs' => ProgramsIndex::class,
]);

test('an admin officer gets a 403 from the faculty, department and program routes', function (string $route) {
    $admin = createAdminOfficer();

    $this->actingAs($admin)->get(route($route))->assertForbidden();
})->with([
    'super-admin.faculties.index',
    'super-admin.departments.index',
    'super-admin.programs.index',
]);

test('an admin officer cannot create, update or delete a program, even in their own department', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $program = Program::factory()->create(['department_id' => $department->id]);

    expect($admin->can('create', Program::class))->toBeFalse();
    expect($admin->can('update', $program))->toBeFalse();
    expect($admin->can('delete', $program))->toBeFalse();
});

test('nobody can delete a program, department or faculty, not even a super admin', function () {
    $superAdmin = createSuperAdmin();
    $program = Program::factory()->create();

    expect($superAdmin->can('delete', $program))->toBeFalse();
    expect($superAdmin->can('delete', $program->department))->toBeFalse();
    expect($superAdmin->can('delete', $program->department->faculty))->toBeFalse();
});

test('a super admin can create, edit and deactivate a program, and each change is audit logged', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(ProgramsIndex::class)
        ->call('startCreate')
        ->set('department_id', (string) $department->id)
        ->set('code', ' bscs ')
        ->set('name', 'BS Computer Science')
        ->call('save')
        ->assertHasNoErrors();

    $program = Program::query()->where('code', 'BSCS')->firstOrFail();
    expect($program->department_id)->toBe($department->id);
    expect($program->is_active)->toBeTrue();

    Livewire::actingAs($superAdmin)
        ->test(ProgramsIndex::class)
        ->call('startEdit', $program->id)
        ->set('name', 'BS Computer Sciences')
        ->call('save')
        ->assertHasNoErrors()
        ->call('toggleActive', $program->id);

    $program->refresh();
    expect($program->name)->toBe('BS Computer Sciences');
    expect($program->is_active)->toBeFalse();

    $events = Activity::query()
        ->where('subject_type', $program->getMorphClass())
        ->where('subject_id', $program->id)
        ->where('causer_id', $superAdmin->id)
        ->pluck('event')
        ->all();

    expect($events)->toBe(['created', 'updated', 'updated']);
});

test('program codes must be unique capital letters', function (string $code, string $rule) {
    Program::factory()->create(['code' => 'BSSE']);

    Livewire::actingAs(createSuperAdmin())
        ->test(ProgramsIndex::class)
        ->call('startCreate')
        ->set('code', $code)
        ->set('name', 'BS Something New')
        ->call('save')
        ->assertHasErrors(['code' => $rule]);
})->with([
    'duplicate' => ['bsse', 'unique'],
    'digits' => ['BS42', 'regex'],
    'separator' => ['BS-SE', 'regex'],
]);

test('a program code cannot change once students are registered with it, but its name can', function () {
    $department = Department::factory()->create();
    createStudentUser($department);
    $program = $department->programs()->firstOrFail();
    $originalCode = $program->code;

    Livewire::actingAs(createSuperAdmin())
        ->test(ProgramsIndex::class)
        ->call('startEdit', $program->id)
        ->set('code', 'BSNEW')
        ->call('save')
        ->assertHasErrors(['code'])
        ->set('code', $originalCode)
        ->set('name', 'BS Renamed Program')
        ->call('save')
        ->assertHasNoErrors();

    expect($program->refresh()->code)->toBe($originalCode);
    expect($program->name)->toBe('BS Renamed Program');
});

test('a super admin can create, edit and deactivate faculties and departments, and each change is audit logged', function () {
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(FacultiesIndex::class)
        ->call('startCreate')
        ->set('name', 'Faculty of Engineering')
        ->set('code', 'foe')
        ->call('save')
        ->assertHasNoErrors();

    $faculty = Faculty::query()->where('code', 'FOE')->firstOrFail();

    Livewire::actingAs($superAdmin)
        ->test(DepartmentsIndex::class)
        ->call('startCreate')
        ->set('faculty_id', (string) $faculty->id)
        ->set('name', 'Electrical Engineering')
        ->set('code', 'ee')
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    $department = Department::query()->where('code', 'EE')->firstOrFail();
    expect($department->faculty_id)->toBe($faculty->id);
    expect($department->is_active)->toBeFalse();

    Livewire::actingAs($superAdmin)->test(FacultiesIndex::class)->call('toggleActive', $faculty->id);
    expect($faculty->refresh()->is_active)->toBeFalse();

    expect(Activity::query()->where('subject_type', $faculty->getMorphClass())->where('subject_id', $faculty->id)->pluck('event')->all())
        ->toBe(['created', 'updated']);
    expect(Activity::query()->where('subject_type', $department->getMorphClass())->where('subject_id', $department->id)->pluck('event')->all())
        ->toBe(['created']);
});

test('activating a department without an active admin officer or active categories shows a warning', function () {
    $department = Department::factory()->create(['is_active' => false, 'name' => 'Computer Science']);

    Livewire::actingAs(createSuperAdmin())
        ->test(DepartmentsIndex::class)
        ->call('toggleActive', $department->id)
        ->assertSet('activation_warning', fn (?string $warning) => str_contains((string) $warning, 'an active Admin Officer')
            && str_contains((string) $warning, 'active application categories'))
        ->assertSee('Department not fully set up');

    expect($department->refresh()->is_active)->toBeTrue();
});

test('activating a fully set up department shows no warning', function () {
    $department = Department::factory()->create(['is_active' => false]);
    createAdminOfficer($department);
    ApplicationCategory::factory()->create(['department_id' => $department->id]);

    Livewire::actingAs(createSuperAdmin())
        ->test(DepartmentsIndex::class)
        ->call('toggleActive', $department->id)
        ->assertSet('activation_warning', null);
});

test('a super admin can filter applications across departments by program', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();

    $studentA = createStudentUser($departmentA);
    createApplicationInDepartment($departmentA, $studentA)->update(['subject' => 'Department A application']);
    $studentB = createStudentUser($departmentB);
    createApplicationInDepartment($departmentB, $studentB)->update(['subject' => 'Department B application']);

    Livewire::actingAs(createSuperAdmin())
        ->test(ApplicationsIndex::class)
        ->set('program_id', (string) $studentB->student->program_id)
        ->assertSee('Department B application')
        ->assertDontSee('Department A application');
});

test('the rename migration renames the seeded faculty in place and leaves a custom name alone', function () {
    $seeded = Faculty::factory()->create(['code' => 'FOC', 'name' => 'Faculty of Computing']);
    $department = Department::factory()->create(['faculty_id' => $seeded->id]);
    $dean = createDean($seeded);
    $dean->update(['name' => 'Dean, Faculty of Computing']);
    $other = Faculty::factory()->create(['name' => 'Faculty of Computing Studies']);

    (require database_path('migrations/2026_10_27_173108_rename_faculty_of_computing_to_faculty_of_cs_and_it.php'))->up();

    expect($seeded->refresh()->name)->toBe('Faculty of CS & IT')
        ->and($department->refresh()->faculty_id)->toBe($seeded->id)
        ->and($dean->refresh()->faculty_id)->toBe($seeded->id)
        ->and($dean->name)->toBe('Dean, Faculty of CS & IT')
        ->and($other->refresh()->name)->toBe('Faculty of Computing Studies');
});
