<?php

use App\Livewire\Admin\Timetables\ShareCard;
use App\Models\Department;
use App\Models\TimetableClass;
use App\Models\TimetableShareLink;
use App\Models\TimetableVersion;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
    $this->department = Department::factory()->create();
    $version = TimetableVersion::factory()->published()->create(['department_id' => $this->department->id]);
    TimetableClass::factory()->forSections(['BSSE-3A'])->create([
        'timetable_version_id' => $version->id,
        'day' => 1,
        'starts_at' => '08:00',
        'ends_at' => '09:20',
        'room' => 'Lecture Room # 22',
        'course' => 'Operating Systems (Theory)',
        'teacher' => 'Mr. Ali Khan',
    ]);
    $this->admin = createAdminOfficer($this->department);
    $this->link = TimetableShareLink::factory()->create([
        'department_id' => $this->department->id,
        'created_by' => $this->admin->id,
    ]);
});

test('a valid token shows the published timetable without login', function () {
    $this->get(route('public.timetable', $this->link->token))
        ->assertOk()
        ->assertSee('data-test="timetable-stats"', false);

    $this->get(route('public.timetable', ['token' => $this->link->token, 'mode' => 'teacher', 'term' => 'ali']))
        ->assertOk()
        ->assertSee('Mr. Ali Khan')
        ->assertSee('data-test="workload"', false);
});

test('inactive, replaced and unknown tokens return the same 404', function () {
    $oldToken = $this->link->token;
    $this->link->update(['token' => TimetableShareLink::newToken()]);
    $newToken = $this->link->token;

    $bodies = collect([$oldToken, str_repeat('x', 48)])
        ->map(fn ($token) => $this->get(route('public.timetable', $token))->assertNotFound()->getContent());

    $this->link->update(['is_active' => false]);
    $bodies->push($this->get(route('public.timetable', $newToken))->assertNotFound()->getContent());

    $withoutFontPreloads = fn (string $html): string => preg_replace('/<link rel="preload"[^>]*>|\s+/', '', $html);

    expect($bodies->map($withoutFontPreloads)->unique())->toHaveCount(1);
});

test('the public page has no student data, student search or portal links', function () {
    $student = createStudentUser($this->department);

    $response = $this->get(route('public.timetable', $this->link->token))->assertOk();

    $response->assertDontSee($student->name)
        ->assertDontSee('Student (Reg. No.)')
        ->assertDontSee('value="student"', false)
        ->assertDontSee(route('login'), false)
        ->assertDontSee(route('dashboard'), false);

    $this->get(route('public.timetable', ['token' => $this->link->token, 'mode' => 'student', 'term' => $student->student->registration_no]))
        ->assertOk()
        ->assertDontSee($student->name);
});

test('the public page is noindex and sets no session cookie', function () {
    $response = $this->get(route('public.timetable', $this->link->token))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false);

    expect($response->headers->getCookies())->toBeEmpty();

    $this->get(route('public.timetable', 'unknown-token'))->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});

test('the public page is throttled per IP', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->get(route('public.timetable', $this->link->token))->assertOk();
    }

    $this->get(route('public.timetable', $this->link->token))->assertStatus(429);
});

test('only the department admin officer and super admin may manage the link', function (string $who, bool $allowed) {
    $user = match ($who) {
        'admin' => $this->admin,
        'super' => createSuperAdmin(),
        'other_admin' => createAdminOfficer(),
        'hod' => createHod($this->department),
        'dean' => createDean(),
        'student' => createStudentUser($this->department),
    };

    expect($user->can('manage', [TimetableShareLink::class, $this->department->id]))->toBe($allowed);

    $component = Livewire::actingAs($user)->test(ShareCard::class, ['departmentId' => (string) $this->department->id]);
    in_array($who, ['hod', 'dean', 'student'], true) ? $component->assertForbidden() : $component->assertOk();
})->with([
    ['admin', true], ['super', true], ['other_admin', false], ['hod', false], ['dean', false], ['student', false],
]);

test('regenerating replaces the token and logs it, toggling logs activation', function () {
    $oldToken = $this->link->token;

    Livewire::actingAs($this->admin)->test(ShareCard::class)->call('generate');

    $newToken = $this->link->fresh()->token;
    expect($newToken)->not->toBe($oldToken)->and(strlen($newToken))->toBeGreaterThanOrEqual(40);
    $this->get(route('public.timetable', $oldToken))->assertNotFound();
    $this->get(route('public.timetable', $newToken))->assertOk();

    Livewire::actingAs($this->admin)->test(ShareCard::class)->call('toggleActive');
    $this->get(route('public.timetable', $newToken))->assertNotFound();

    Livewire::actingAs($this->admin)->test(ShareCard::class)->call('toggleActive');
    expect($this->link->fresh()->is_active)->toBeTrue()
        ->and(Activity::query()->where('subject_type', TimetableShareLink::class)->whereIn('event', ['regenerated', 'deactivated', 'activated'])->count())->toBe(3);
});

test('an admin creates the first link and it is logged', function () {
    $this->link->delete();

    Livewire::actingAs($this->admin)->test(ShareCard::class)->call('generate');

    expect(TimetableShareLink::query()->where('department_id', $this->department->id)->count())->toBe(1)
        ->and(Activity::query()->where('subject_type', TimetableShareLink::class)->where('event', 'created')->count())->toBe(1);
});

test('an admin of another department cannot touch this link through the card', function () {
    $other = createAdminOfficer();
    $oldToken = $this->link->token;

    Livewire::actingAs($other)->test(ShareCard::class, ['departmentId' => (string) $this->department->id])->call('generate');

    expect($this->link->fresh()->token)->toBe($oldToken);
});
