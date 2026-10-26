<?php

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\DepartmentSeeder;
use Database\Seeders\FacultySeeder;
use Database\Seeders\ProgramSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        // The array cache store persists across tests in the same
        // process; Setting::get()/set() cache forever, so a value set in
        // one test would otherwise leak into the next.
        Cache::flush();
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Create the three application roles. RefreshDatabase wipes them between
 * tests, so call this in any test that assigns or checks a role.
 */
function createRoles(): void
{
    foreach (RoleName::cases() as $role) {
        Role::findOrCreate($role->value);
    }
}

/**
 * The seeded academic structure: Faculty of Computing, the SE/IT/CS
 * departments and the BSSE/BSDS/BSAI programs under SE.
 */
function seedAcademicStructure(): void
{
    test()->seed([FacultySeeder::class, DepartmentSeeder::class, ProgramSeeder::class]);
}

/**
 * A student user, with a Student profile, in the given (or a fresh) department.
 * Their program is one of the department's programs.
 */
function createStudentUser(?Department $department = null): User
{
    $department ??= Department::factory()->create();

    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(RoleName::Student->value);

    $program = Program::withoutGlobalScopes()->where('department_id', $department->id)->first()
        ?? Program::factory()->create(['department_id' => $department->id]);

    Student::factory()->create(['user_id' => $user->id, 'program_id' => $program->id]);

    return $user->refresh();
}

/**
 * An Admin Officer user in the given (or a fresh) department.
 */
function createAdminOfficer(?Department $department = null): User
{
    $department ??= Department::factory()->create();

    $user = User::factory()->create(['department_id' => $department->id]);
    $user->assignRole(RoleName::AdminOfficer->value);

    return $user;
}

/**
 * A Super Admin user, which belongs to no single department.
 */
function createSuperAdmin(): User
{
    $user = User::factory()->create(['department_id' => null]);
    $user->assignRole(RoleName::SuperAdmin->value);

    return $user;
}

/**
 * An application for the given student, in a fresh category of the
 * given department.
 */
function createApplicationInDepartment(Department $department, User $student): Application
{
    $category = ApplicationCategory::factory()->create(['department_id' => $department->id]);

    return Application::factory()->create([
        'student_id' => $student->student->id,
        'department_id' => $department->id,
        'category_id' => $category->id,
    ]);
}

/**
 * A small timetable workbook written to a temp file, shaped like the real
 * department file: a weekday sheet with a merged two-slot class, a floor
 * merged down over two rooms, a time override, a whole-semester
 * "Combined" class and an unreadable cell; and a weekend sheet with the
 * sections on the same line as the course. Returns the file path.
 */
function makeTimetableWorkbook(): string
{
    $spreadsheet = new Spreadsheet;

    $weekdays = $spreadsheet->getActiveSheet()->setTitle('Time Table');
    $weekdays->fromArray([
        ['Department of Software Engineering {Monday to Friday Timetable}'],
        [],
        ['Monday ', 'Rooms', null, '8:00-9:20', '9:20-10:40', '10:45-12:05', '01:30 - 02:50'],
        [null, 'Lecture Room # 22', 'Ist Floor ', "Software Engineering \nBSSE-3A & BSSE-3B\nMr. Muhammad Hamza ", null, "Operating Systems (Lab)\nBSSE-5A\nMr. Muhammad Abdullah", "Pre Calculus \nBSSE-1\nCombined\nMr. Zahid Ali\n(01:30 - 03:30)"],
        [null, 'Computer Lab-7', null, "Applied Physics (Thoery + Lab)\nBSSE-1A\nMr. Zahid Ali", "Database Systems \nBSSE-1B\nMs. Laiba Tahir", 'Lab closed for maintenance'],
    ]);
    $weekdays->mergeCells('A3:A5');
    $weekdays->mergeCells('C4:C5');
    $weekdays->mergeCells('D4:E4');

    $weekend = $spreadsheet->createSheet()->setTitle('1M Timetable ');
    $weekend->fromArray([
        ['Saturday'],
        ['Saturday', 'Rooms', '09:00 - 11:40', '11:40 - 02:20'],
        [null, 'Lecture Room # 23', "Data Visualization BSAI-7C / BSAI-8A/BSDS-8A\nMr. Usmar"],
    ]);

    $path = tempnam(sys_get_temp_dir(), 'timetable').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

/**
 * A student user in the given department whose program has the given
 * code (e.g. "BSSE"), in the given semester and timetable section.
 */
function createTimetableStudent(Department $department, string $programCode, int $semester, ?string $section = null): User
{
    $user = createStudentUser($department);

    $program = Program::withoutGlobalScopes()->firstOrCreate(
        ['code' => $programCode],
        ['department_id' => $department->id, 'name' => "BS {$programCode}"],
    );

    $user->student->update(['program_id' => $program->id, 'current_semester' => $semester, 'section' => $section]);

    return $user->refresh();
}
