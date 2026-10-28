<?php

use App\Enums\UserStatus;
use App\Livewire\Admin\Students\Show as StudentShow;
use App\Livewire\Auth\Register;
use App\Livewire\Student\Applications\Create as ApplicationCreate;
use App\Livewire\SuperAdmin\RegistrationFormats\Index as FormatsIndex;
use App\Models\ApplicationCategory;
use App\Models\RegistrationFormat;
use App\Models\Student;
use App\Models\User;
use App\Support\Registration\FormatTemplate;
use App\Support\Registration\StudentEmailDomain;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
 * The old-format examples below are PLACEHOLDERS that follow the one
 * example given for this feature (email "bsemf21-040@superior.edu.pk").
 * Replace them with real old emails and registration numbers.
 */
const OLD_EMAIL = 'bsemf21-040@superior.edu.pk';
const OLD_REGISTRATION_NO = 'BSEMF21-040';

beforeEach(function () {
    createRoles();
    seedAcademicStructure();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function oldStudentData(array $overrides = []): array
{
    return [
        'name' => 'Old Student',
        'program' => 'BSSE',
        'registration_no' => OLD_REGISTRATION_NO,
        'email' => OLD_EMAIL,
        'current_semester' => '',
        'password' => 'password',
        'password_confirmation' => 'password',
        ...$overrides,
    ];
}

function oldFormat(array $attributes = []): RegistrationFormat
{
    return RegistrationFormat::factory()->alumni()->create($attributes);
}

test('a template matches the old email and registration number and nothing near them', function () {
    $email = 'bsem{BATCH}-{DIGITS:3}';

    expect(FormatTemplate::match($email, 'bsemf21-040'))->toBe(['batch' => 'F21', 'program' => null])
        ->and(FormatTemplate::match('BSEM{BATCH}-{DIGITS:3}', OLD_REGISTRATION_NO))->toBe(['batch' => 'F21', 'program' => null])
        ->and(FormatTemplate::match($email, 'BSEMS23-001'))->toBe(['batch' => 'S23', 'program' => null]);

    foreach (['bsemf21-04', 'bsemf21-0401', 'xbsemf21-040', 'bsemf2-040', 'bsemf21-04a', "bsemf21-040\n", 'bsemf21-040 ', '', 'bsemg21-040'] as $near) {
        expect(FormatTemplate::match($email, $near))->toBeNull();
    }
});

test('templates reject raw regex and anything unbounded or oversized', function (string $template) {
    expect(FormatTemplate::problem($template))->not->toBeNull();
})->with([
    'raw regex' => ['(a+)+$'],
    'wildcard' => ['bsem.*'],
    'plus' => ['bsem{DIGITS:1-4}+'],
    'character class' => ['bsem[0-9]'],
    'unknown placeholder' => ['bsem{ANYTHING}'],
    'no upper bound' => ['bsem{DIGITS:1-}'],
    'repeat too large' => ['bsem{DIGITS:1-99}'],
    'max below min' => ['bsem{DIGITS:5-2}'],
    'zero digits' => ['bsem{DIGITS:0-2}'],
    'unclosed brace' => ['bsem{DIGITS:3'],
    'batch twice' => ['{BATCH}{BATCH}'],
    'at sign' => ['bsem@evil'],
    'space' => ['bsem 123'],
    'empty' => [''],
    'too long' => [str_repeat('a', 81)],
    'too many placeholders' => ['{DIGITS:1}{DIGITS:1}{DIGITS:1}{DIGITS:1}{DIGITS:1}{DIGITS:1}{DIGITS:1}'],
]);

test('a valid template compiles to an anchored pattern with only bounded repeats', function () {
    $pattern = FormatTemplate::pattern('bsem{BATCH}-{DIGITS:1-4}{LETTERS:2}');

    expect($pattern)->toStartWith('/\A')->toEndWith('\z/iD')->not->toContain('*')->not->toContain('+')
        ->and(FormatTemplate::problem('bsem{BATCH}-{DIGITS:1-4}'))->toBeNull();
});

test('the student email domain is locked to superior.edu.pk', function (string $email, bool $valid) {
    expect(StudentEmailDomain::isValid($email))->toBe($valid);
})->with([
    'plain' => ['bsemf21-040@superior.edu.pk', true],
    'upper case' => ['BSEMF21-040@SUPERIOR.EDU.PK', true],
    'padded' => ['  bsemf21-040@superior.edu.pk  ', true],
    'gmail' => ['bsemf21-040@gmail.com', false],
    'yahoo' => ['bsemf21-040@yahoo.com', false],
    'outlook' => ['bsemf21-040@outlook.com', false],
    'domain suffix trick' => ['user@superior.edu.pk.evil.com', false],
    'query trick' => ['user@evil.com?@superior.edu.pk', false],
    'double at' => ['user@evil.com@superior.edu.pk', false],
    'subdomain' => ['user@mail.superior.edu.pk', false],
    'lookalike' => ['user@superior-edu.pk', false],
    'no local part' => ['@superior.edu.pk', false],
    'space in local part' => ['us er@superior.edu.pk', false],
    'newline inside' => ["user\n@superior.edu.pk", false],
    'empty' => ['', false],
]);

test('a student registers with an old email and registration number, with no current semester, and still gets the verification email', function () {
    Notification::fake();
    oldFormat();

    $this->post(route('register.store'), oldStudentData())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $user = User::query()->where('email', OLD_EMAIL)->firstOrFail();
    $student = $user->student;

    expect($user->status)->toBe(UserStatus::PendingVerification)
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->hasRole('student'))->toBeTrue()
        ->and($student->registration_no)->toBe(OLD_REGISTRATION_NO)
        ->and($student->is_alumni)->toBeTrue()
        ->and($student->current_semester)->toBeNull()
        ->and($student->batch)->toBe('F21')
        ->and($student->program->code)->toBe('BSSE')
        ->and($student->semesterLabel())->toBe('Alumni');

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('the current program-based format keeps working and its semester is still required', function () {
    $this->post(route('register.store'), [
        'name' => 'Ayesha Khan', 'program' => 'BSSE', 'registration_no' => 'SU92-BSSEM-F22-171',
        'email' => 'su92-bssem-f22-171@superior.edu.pk', 'current_semester' => '',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasErrors('current_semester');

    $this->post(route('register.store'), [
        'name' => 'Ayesha Khan', 'program' => 'BSSE', 'registration_no' => 'SU92-BSSEM-F22-171',
        'email' => 'su92-bssem-f22-171@superior.edu.pk', 'current_semester' => '5',
        'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    expect(Student::query()->sole()->is_alumni)->toBeFalse();
});

test('a rule that is not for alumni still asks for the current semester', function () {
    RegistrationFormat::factory()->create();

    $this->post(route('register.store'), oldStudentData())->assertSessionHasErrors('current_semester');
    $this->post(route('register.store'), oldStudentData(['current_semester' => '7']))->assertSessionHasNoErrors();

    $student = User::query()->where('email', OLD_EMAIL)->firstOrFail()->student;
    expect($student->current_semester)->toBe(7)->and($student->is_alumni)->toBeFalse()->and($student->batch)->toBe('F21');
});

test('without an active rule, or with a disabled one, the old format is refused', function () {
    $this->post(route('register.store'), oldStudentData())->assertSessionHasErrors('registration_no');

    oldFormat(['is_active' => false]);

    $this->post(route('register.store'), oldStudentData())->assertSessionHasErrors('registration_no');

    expect(User::query()->where('email', OLD_EMAIL)->exists())->toBeFalse();
});

test('an old registration number needs the email that fits the same rule', function () {
    oldFormat();

    $this->post(route('register.store'), oldStudentData(['email' => 'someone.else@superior.edu.pk']))->assertSessionHasErrors('email');
    $this->post(route('register.store'), oldStudentData(['email' => 'bsemf22-040@superior.edu.pk']))->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
});

test('a rule tied to a program or batch is not used for another one', function () {
    oldFormat(['program_code' => 'BSDS']);
    $this->post(route('register.store'), oldStudentData())->assertSessionHasErrors('registration_no');

    RegistrationFormat::query()->delete();
    oldFormat(['batch' => 'F20']);
    $this->post(route('register.store'), oldStudentData())->assertSessionHasErrors('registration_no');

    expect(User::query()->count())->toBe(0);
});

test('no email outside superior.edu.pk is accepted, for the current or an additional format', function (string $email) {
    oldFormat(['email_template' => '{LETTERS:1-10}{BATCH}-{DIGITS:3}']);

    $this->post(route('register.store'), oldStudentData(['email' => $email]))->assertSessionHasErrors('email');
    $this->post(route('register.store'), [
        'name' => 'Ayesha Khan', 'program' => 'BSSE', 'registration_no' => 'SU92-BSSEM-F22-171',
        'email' => $email, 'current_semester' => '5', 'password' => 'password', 'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe(0);
})->with([
    'gmail' => 'bsemf21-040@gmail.com',
    'yahoo' => 'bsemf21-040@yahoo.com',
    'outlook' => 'bsemf21-040@outlook.com',
    'suffix trick' => 'bsemf21-040@superior.edu.pk.evil.com',
    'query trick' => 'bsemf21-040@evil.com?@superior.edu.pk',
    'subdomain' => 'bsemf21-040@mail.superior.edu.pk',
]);

test('email and registration number stay unique for old formats too', function () {
    oldFormat();

    $this->post(route('register.store'), oldStudentData())->assertSessionHasNoErrors();
    auth()->logout();

    $this->post(route('register.store'), oldStudentData())->assertSessionHasErrors(['email', 'registration_no']);

    expect(User::query()->where('email', OLD_EMAIL)->count())->toBe(1)->and(Student::query()->count())->toBe(1);
});

test('the register form hides the semester only for a passed-out format', function () {
    oldFormat();

    Livewire::test(Register::class)
        ->assertSeeHtml('wire:model.live="current_semester"')
        ->set('program', 'BSSE')
        ->set('registration_no', OLD_REGISTRATION_NO)
        ->set('email', OLD_EMAIL)
        ->assertDontSeeHtml('wire:model.live="current_semester"')
        ->set('email', 'su92-bssem-f22-171@superior.edu.pk')
        ->assertSeeHtml('wire:model.live="current_semester"');
});

test('the Super Admin adds, edits, disables and enables a format, every step is logged, and a pasted domain is stripped', function () {
    $superAdmin = createSuperAdmin();

    $component = Livewire::actingAs($superAdmin)->test(FormatsIndex::class)
        ->call('startCreate')
        ->set('name', 'Batch F21 old format')
        ->set('email_template', 'bsem{BATCH}-{DIGITS:3}@superior.edu.pk')
        ->set('registration_no_template', 'BSEM{BATCH}-{DIGITS:3}')
        ->set('is_alumni', true)
        ->call('save')
        ->assertHasNoErrors();

    $format = RegistrationFormat::query()->sole();
    expect($format->email_template)->toBe('bsem{BATCH}-{DIGITS:3}')
        ->and($format->is_alumni)->toBeTrue()
        ->and($format->is_active)->toBeTrue();

    $component->call('startEdit', $format->id)->set('name', 'Renamed')->call('save')->assertHasNoErrors();
    $component->call('toggleActive', $format->id);
    expect($format->refresh()->name)->toBe('Renamed')->and($format->is_active)->toBeFalse();

    $component->call('toggleActive', $format->id);
    expect($format->refresh()->is_active)->toBeTrue();

    $descriptions = Activity::query()->where('subject_type', $format->getMorphClass())->orderBy('id')->pluck('description')->all();
    expect($descriptions)->toBe(['registration format created', 'registration format updated', 'registration format disabled', 'registration format enabled']);
});

test('a format with any other domain, raw regex, or no batch is refused', function () {
    $component = Livewire::actingAs(createSuperAdmin())->test(FormatsIndex::class)
        ->call('startCreate')
        ->set('name', 'Bad')
        ->set('registration_no_template', 'BSEM{BATCH}-{DIGITS:3}');

    foreach (['bsem{BATCH}-{DIGITS:3}@gmail.com', 'bsem@superior.edu.pk.evil.com', 'a@b@superior.edu.pk', 'bsem{BATCH}@yahoo.com'] as $email) {
        $component->set('email_template', $email)->call('save')->assertHasErrors('email_template');
    }

    $component->set('email_template', '(a+)+$')->call('save')->assertHasErrors('email_template');
    $component->set('email_template', 'bsem{DIGITS:3}')->set('registration_no_template', 'BSEM-{DIGITS:3}')->call('save')->assertHasErrors('batch');
    $component->set('registration_no_template', 'BSEM-{DIGITS:3}')->set('batch', 'X9')->call('save')->assertHasErrors('batch');

    expect(RegistrationFormat::query()->count())->toBe(0);
});

test('the test box says what a format accepts and rejects', function () {
    oldFormat(['name' => 'Batch F21 old format']);
    oldFormat(['name' => 'Disabled one', 'email_template' => 'dis{DIGITS:3}', 'registration_no_template' => 'DIS-{DIGITS:3}', 'batch' => 'F19', 'is_active' => false]);

    Livewire::actingAs(createSuperAdmin())->test(FormatsIndex::class)
        ->set('test_email', OLD_EMAIL)->set('test_registration_no', OLD_REGISTRATION_NO)
        ->assertSee('Accepted by "Batch F21 old format"')
        ->assertSee('Alumni: no current semester')
        ->set('test_email', 'bsemf21-040@gmail.com')
        ->assertSee('Rejected: the email must be a plain address ending in @superior.edu.pk')
        ->set('test_email', 'someone@superior.edu.pk')
        ->assertSee('Rejected: no active format accepts this email and registration number together.')
        ->set('test_email', 'dis123@superior.edu.pk')->set('test_registration_no', 'DIS-123')
        ->assertSee('only the disabled format "Disabled one" matches')
        ->set('test_email', 'su92-bssem-f22-171@superior.edu.pk')->set('test_registration_no', 'SU92-BSSEM-F22-171')
        ->assertSee('Accepted by the current program-based format.');
});

test('only the Super Admin can manage formats', function (string $who) {
    $user = match ($who) {
        'admin officer' => createAdminOfficer(),
        'hod' => createHod(),
        'dean' => createDean(),
        'student' => createStudentUser(),
    };

    $this->actingAs($user)->get(route('super-admin.registration-formats.index'))->assertForbidden();
    Livewire::actingAs($user)->test(FormatsIndex::class)->assertForbidden();
    expect(RegistrationFormat::query()->count())->toBe(0);
})->with(['admin officer', 'hod', 'dean', 'student']);

test('the Super Admin marks an email verified, which activates the student, and it is logged', function () {
    oldFormat();
    $this->post(route('register.store'), oldStudentData())->assertSessionHasNoErrors();
    auth()->logout();
    $user = User::query()->where('email', OLD_EMAIL)->firstOrFail();
    $superAdmin = createSuperAdmin();

    expect($superAdmin->can('markEmailVerified', $user->student))->toBeTrue();

    Livewire::actingAs($superAdmin)->test(StudentShow::class, ['student' => $user->student])
        ->assertSeeHtml('data-test="mark-email-verified"')
        ->call('markEmailVerified')
        ->assertHasNoErrors();

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull()->and($user->status)->toBe(UserStatus::Active);

    $log = Activity::query()->where('description', 'student email marked verified')->sole();
    expect($log->causer_id)->toBe($superAdmin->id)->and($log->subject_id)->toBe($user->id);

    expect($superAdmin->can('markEmailVerified', $user->student->refresh()))->toBeFalse();

    auth()->logout();
    $this->post(route('login.store'), ['email' => OLD_EMAIL, 'password' => 'password']);
    $this->assertAuthenticatedAs($user);
    $this->get(route('student.dashboard'))->assertOk()->assertSee('Alumni');
    $this->get(route('student.profile'))->assertOk()->assertDontSeeHtml('data-test="update-semester-button"');
});

test('nobody but the Super Admin can mark an email verified', function () {
    oldFormat();
    $this->post(route('register.store'), oldStudentData())->assertSessionHasNoErrors();
    auth()->logout();
    $student = User::query()->where('email', OLD_EMAIL)->firstOrFail()->student;
    $admin = createAdminOfficer($student->user->department);

    expect($admin->can('markEmailVerified', $student))->toBeFalse();

    Livewire::actingAs($admin)->test(StudentShow::class, ['student' => $student])
        ->assertDontSeeHtml('data-test="mark-email-verified"')
        ->call('markEmailVerified')
        ->assertForbidden();

    Livewire::actingAs($student->user)->test(StudentShow::class, ['student' => $student])->call('markEmailVerified')->assertForbidden();

    expect($student->user->refresh()->email_verified_at)->toBeNull();
});

test('an alumni can submit an application without a semester', function () {
    oldFormat();
    $this->post(route('register.store'), oldStudentData())->assertSessionHasNoErrors();
    auth()->logout();
    $user = User::query()->where('email', OLD_EMAIL)->firstOrFail();
    $user->forceFill(['email_verified_at' => now(), 'status' => UserStatus::Active])->save();
    $category = ApplicationCategory::factory()->create(['department_id' => $user->department_id, 'is_active' => true]);

    Livewire::actingAs($user)->test(ApplicationCreate::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Transcript request')
        ->set('body', 'I need my transcript.')
        ->call('submit')
        ->assertHasNoErrors();

    expect($user->student->applications()->sole()->semester_at_submission)->toBeNull();
});
