<?php

use App\Enums\ApplicationStatus;
use App\Enums\MessageType;
use App\Livewire\Admin\Applications\Index;
use App\Livewire\Admin\Applications\Show;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationMessage;
use App\Models\Department;
use App\Models\Program;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('an admin officer only sees applications from their own department', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $admin = createAdminOfficer($departmentA);

    $studentA = createStudentUser($departmentA);
    $categoryA = ApplicationCategory::factory()->create(['department_id' => $departmentA->id]);
    $ownApplication = Application::factory()->create([
        'student_id' => $studentA->student->id,
        'department_id' => $departmentA->id,
        'category_id' => $categoryA->id,
        'subject' => 'Own department application',
    ]);

    $studentB = createStudentUser($departmentB);
    $categoryB = ApplicationCategory::factory()->create(['department_id' => $departmentB->id]);
    Application::factory()->create([
        'student_id' => $studentB->student->id,
        'department_id' => $departmentB->id,
        'category_id' => $categoryB->id,
        'subject' => 'Other department application',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('Own department application')
        ->assertDontSee('Other department application');

    expect($ownApplication)->not->toBeNull();
});

test('a super admin sees applications from every department and can filter to one', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $superAdmin = createSuperAdmin();

    $applicationA = createApplicationInDepartment($departmentA, createStudentUser($departmentA));
    $applicationA->update(['subject' => 'Department A application']);

    $applicationB = createApplicationInDepartment($departmentB, createStudentUser($departmentB));
    $applicationB->update(['subject' => 'Department B application']);

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->assertSee('Department A application')
        ->assertSee('Department B application')
        ->set('department_id', (string) $departmentA->id)
        ->assertSee('Department A application')
        ->assertDontSee('Department B application');
});

test('search matches by student name', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $student->update(['name' => 'Ayesha Khan']);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);
    Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'subject' => 'Searchable subject',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'Ayesha')
        ->assertSee('Searchable subject');
});

test('search matches by application number', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);
    $application = Application::createWithApplicationNumber([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'subject' => 'Searchable subject',
        'body' => 'Body text.',
        'semester_at_submission' => 3,
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', $application->application_no)
        ->assertSee('Searchable subject');
});

test('the status filter can be reset back to showing everything', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);

    Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'subject' => 'Submitted one',
        'status' => ApplicationStatus::Submitted,
    ]);
    Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'subject' => 'Resolved one',
        'status' => ApplicationStatus::Resolved,
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('status', 'resolved')
        ->assertDontSee('Submitted one')
        ->set('status', '')
        ->assertSee('Submitted one')
        ->assertSee('Resolved one');
});

test('applications can be filtered by the student\'s roll number, batch and program', function (string $filter, string $value) {
    $department = Department::factory()->create();
    $bsse = Program::factory()->create(['department_id' => $department->id, 'code' => 'BSSE']);
    $bsds = Program::factory()->create(['department_id' => $department->id, 'code' => 'BSDS']);
    $admin = createAdminOfficer($department);

    $matching = createStudentUser($department);
    $matching->student->update(['registration_no' => 'SU92-BSSEM-F22-171', 'batch' => 'F22', 'program_id' => $bsse->id]);
    createApplicationInDepartment($department, $matching)->update(['subject' => 'Matching student application']);

    $other = createStudentUser($department);
    $other->student->update(['registration_no' => 'SU92-BSDSM-F23-045', 'batch' => 'F23', 'program_id' => $bsds->id]);
    createApplicationInDepartment($department, $other)->update(['subject' => 'Other student application']);

    $value = $filter === 'program_id' ? (string) $bsse->id : $value;

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set($filter, $value)
        ->assertSee('Matching student application')
        ->assertDontSee('Other student application');
})->with([
    'partial roll number' => ['roll_no', 'F22-171'],
    'batch' => ['batch', 'F22'],
    'program' => ['program_id', ''],
]);

test('the program filter only offers the admin officer\'s own department programs', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    Program::factory()->create(['department_id' => $department->id, 'name' => 'BS Own Program']);
    Program::factory()->create(['name' => 'BS Foreign Program']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('BS Own Program')
        ->assertDontSee('BS Foreign Program');
});

test('clearFilters resets search, every dropdown and the date range', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    $component = Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('search', 'something')
        ->set('status', 'resolved')
        ->set('priority', 'urgent')
        ->set('category_id', '1')
        ->set('semester', '3')
        ->set('date_from', '2026-01-01')
        ->set('date_to', '2026-01-31')
        ->set('roll_no', 'F22-171')
        ->set('batch', 'F22')
        ->set('program_id', '1');

    expect($component->get('hasActiveFilters'))->toBeTrue();

    $component->call('clearFilters');

    expect($component->get('hasActiveFilters'))->toBeFalse();
    expect($component->get('roll_no'))->toBe('');
    expect($component->get('batch'))->toBe('');
    expect($component->get('program_id'))->toBe('');
    expect($component->get('search'))->toBe('');
    expect($component->get('status'))->toBe('');
    expect($component->get('priority'))->toBe('');
    expect($component->get('category_id'))->toBe('');
    expect($component->get('semester'))->toBe('');
    expect($component->get('date_from'))->toBe('');
    expect($component->get('date_to'))->toBe('');
});

test('visiting the index with a search query string (as the top-bar search does) filters the list', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $student->update(['name' => 'Zarmeen Abbas']);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);
    Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'subject' => 'Findable via top bar search',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.applications.index', ['search' => 'Zarmeen']))
        ->assertOk()
        ->assertSee('Findable via top bar search');
});

test('status filter narrows the results', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);

    Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'subject' => 'Submitted one',
        'status' => ApplicationStatus::Submitted,
    ]);
    Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
        'subject' => 'Resolved one',
        'status' => ApplicationStatus::Resolved,
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->set('status', 'resolved')
        ->assertSee('Resolved one')
        ->assertDontSee('Submitted one');
});

test('an application with an unread student response shows a new response badge', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    ApplicationMessage::create([
        'application_id' => $application->id,
        'sender_id' => $student->id,
        'type' => MessageType::StudentResponse->value,
        'body' => 'Here is my response.',
    ]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertSee('New response');
});

test('the new response badge disappears once the admin has opened the application', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    ApplicationMessage::create([
        'application_id' => $application->id,
        'sender_id' => $student->id,
        'type' => MessageType::StudentResponse->value,
        'body' => 'Here is my response.',
    ]);

    Livewire::actingAs($admin)->test(Show::class, ['application' => $application]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->assertDontSee('New response');
});
