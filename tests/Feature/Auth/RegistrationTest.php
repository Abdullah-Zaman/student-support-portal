<?php

use App\Enums\UserStatus;
use App\Livewire\Auth\Register;
use App\Livewire\SuperAdmin\Programs\Index as ProgramsIndex;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Program;
use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Livewire;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());

    createRoles();
    seedAcademicStructure();
});

function validRegistrationData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ayesha Khan',
        'program' => 'BSSE',
        'registration_no' => 'SU92-BSSEM-F22-171',
        'email' => 'su92-bssem-f22-171@superior.edu.pk',
        'current_semester' => '5',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('a student can register with valid data', function () {
    $response = $this->post(route('register.store'), validRegistrationData());

    $response->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();

    $user = User::query()->where('email', 'su92-bssem-f22-171@superior.edu.pk')->firstOrFail();

    expect($user->status)->toBe(UserStatus::PendingVerification);
    expect($user->hasRole('student'))->toBeTrue();
    expect($user->department->code)->toBe('SE');

    $student = $user->student;
    expect($student)->not->toBeNull();
    expect($student->registration_no)->toBe('SU92-BSSEM-F22-171');
    expect($student->program->code)->toBe('BSSE');
    expect($student->current_semester)->toBe(5);
    expect($student->batch)->toBe('F22');
});

test('registration number is uppercased and email is lowercased before storing', function () {
    $this->post(route('register.store'), validRegistrationData([
        'registration_no' => 'su92-bssem-f22-171',
        'email' => 'SU92-BSSEM-F22-171@SUPERIOR.EDU.PK',
    ]))->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'su92-bssem-f22-171@superior.edu.pk')->first();

    expect($user)->not->toBeNull();
    expect($user->student->registration_no)->toBe('SU92-BSSEM-F22-171');
});

test('registration is rejected when the email domain is wrong', function () {
    $response = $this->post(route('register.store'), validRegistrationData([
        'email' => 'su92-bssem-f22-171@gmail.com',
    ]));

    $response->assertSessionHasErrors([
        'email' => 'Use your university email ending in @superior.edu.pk.',
    ]);
    $this->assertGuest();
});

test('registration is rejected when the email does not match the registration number', function () {
    $response = $this->post(route('register.store'), validRegistrationData([
        'email' => 'someone-else@superior.edu.pk',
    ]));

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('registration is rejected when the registration number format is wrong', function () {
    $response = $this->post(route('register.store'), validRegistrationData([
        'registration_no' => 'NOT-A-VALID-FORMAT',
    ]));

    $response->assertSessionHasErrors('registration_no');
    $this->assertGuest();
});

test('registration is rejected when the registration number program code does not match the selected program', function () {
    $response = $this->post(route('register.store'), validRegistrationData([
        'program' => 'BSDS',
    ]));

    $response->assertSessionHasErrors('registration_no');
    $this->assertGuest();
});

test('a program added by the super admin makes its registration numbers valid, in its own department', function () {
    $computerScience = Department::query()->where('code', 'CS')->firstOrFail();
    $computerScience->update(['is_active' => true]);
    $bscsData = validRegistrationData([
        'program' => 'BSCS',
        'registration_no' => 'SU92-BSCSM-F24-012',
        'email' => 'su92-bscsm-f24-012@superior.edu.pk',
    ]);

    $this->post(route('register.store'), $bscsData)->assertSessionHasErrors(['program', 'registration_no']);

    Livewire::actingAs(createSuperAdmin())
        ->test(ProgramsIndex::class)
        ->call('startCreate')
        ->set('department_id', (string) $computerScience->id)
        ->set('code', 'bscs')
        ->set('name', 'BS Computer Science')
        ->call('save')
        ->assertHasNoErrors();
    auth()->logout();

    $this->post(route('register.store'), $bscsData)->assertSessionHasNoErrors();

    $user = User::query()->where('email', 'su92-bscsm-f24-012@superior.edu.pk')->firstOrFail();
    expect($user->department_id)->toBe($computerScience->id);
    expect($user->student->program->code)->toBe('BSCS');
    expect($user->student->batch)->toBe('F24');
});

test('registration is rejected for a deactivated program', function () {
    $this->post(route('register.store'), validRegistrationData(['registration_no' => 'NOT-VALID']));

    Program::query()->where('code', 'BSSE')->firstOrFail()->update(['is_active' => false]);

    $this->post(route('register.store'), validRegistrationData())
        ->assertSessionHasErrors(['registration_no' => 'Registration is not open for this program.', 'program']);
    $this->assertGuest();
});

test('registration is rejected for a program whose department or faculty is deactivated', function (string $model, string $code) {
    $model::query()->where('code', $code)->firstOrFail()->update(['is_active' => false]);

    $this->post(route('register.store'), validRegistrationData())
        ->assertSessionHasErrors(['registration_no' => 'Registration is not open for this program.']);
    $this->assertGuest();
})->with([
    'department' => [Department::class, 'SE'],
    'faculty' => [Faculty::class, 'FOC'],
]);

test('only programs open for registration are offered on the registration form', function () {
    Program::query()->where('code', 'BSDS')->firstOrFail()->update(['is_active' => false]);

    Livewire::test(Register::class)
        ->assertSee('BS Software Engineering')
        ->assertDontSee('BS Data Science');
});

test('a student whose program, department and faculty were deactivated can still log in', function () {
    $department = Department::query()->where('code', 'SE')->firstOrFail();
    $student = createStudentUser($department);

    Program::query()->update(['is_active' => false]);
    $department->update(['is_active' => false]);
    $department->faculty->update(['is_active' => false]);

    $this->post(route('login.store'), ['email' => $student->email, 'password' => 'password'])
        ->assertSessionHasNoErrors();

    $this->assertAuthenticatedAs($student);
});

test('registration is rejected when the email is already taken', function () {
    User::factory()->create(['email' => 'su92-bssem-f22-171@superior.edu.pk']);

    $response = $this->post(route('register.store'), validRegistrationData());

    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('registration is rejected when the registration number is already taken', function () {
    $existing = createStudentUser();
    $existing->student->update(['registration_no' => 'SU92-BSSEM-F22-171']);

    $response = $this->post(route('register.store'), validRegistrationData());

    $response->assertSessionHasErrors('registration_no');
    $this->assertGuest();
});

test('an unverified student is blocked from the dashboard', function () {
    $this->post(route('register.store'), validRegistrationData())
        ->assertSessionHasNoErrors();

    $response = $this->get(route('student.dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('registration is rate limited to 20 attempts per minute per ip', function () {
    for ($i = 0; $i < 20; $i++) {
        $this->post(route('register.store'), validRegistrationData([
            'registration_no' => sprintf('SU92-BSSEM-F22-%d', $i + 1),
            'email' => sprintf('su92-bssem-f22-%d@superior.edu.pk', $i + 1),
        ]));
        $this->post(route('logout'));
    }

    $response = $this->post(route('register.store'), validRegistrationData([
        'registration_no' => 'SU92-BSSEM-F22-999',
        'email' => 'su92-bssem-f22-999@superior.edu.pk',
    ]));

    $response->assertStatus(429);
});

test('the registration intro lists the programs open for registration from the database', function () {
    Program::query()->where('code', 'BSDS')->firstOrFail()->update(['is_active' => false]);
    Program::factory()->create(['code' => 'BSIT', 'name' => 'BS Information Technology', 'department_id' => Department::query()->where('code', 'SE')->value('id')]);

    Livewire::test(Register::class)
        ->assertSee('Only students of BSAI, BSIT and BSSE with a university email can register.')
        ->assertDontSee('BS SE, BS DS, BS AI');
});
