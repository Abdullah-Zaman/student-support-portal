<?php

use App\Actions\Correspondence\LogExternalReply;
use App\Actions\Correspondence\ReplyToCorrespondence;
use App\Actions\Forwarding\ForwardApplication;
use App\Enums\ApplicationEventType;
use App\Enums\CorrespondenceParty;
use App\Enums\ForwardTarget;
use App\Livewire\Admin\Applications\Show;
use App\Livewire\Correspondence\Thread;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ApplicationForwardedForReview;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
    Setting::set(ForwardApplication::REGISTRAR_EMAIL_SETTING, 'registrar@superior.edu.pk');
});

/**
 * One department and faculty with an Admin Officer, HOD and Dean, and an
 * application each of them can act on: the HOD and Dean have each been
 * forwarded it, so they may open it.
 *
 * @return array{admin: User, hod: User, dean: User, application: Application}
 */
function flexibleStaff(): array
{
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $dean = createDean($faculty);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    recordForward($application, ForwardTarget::Dean, $admin, 'For approval.', $dean);

    return compact('admin', 'hod', 'dean', 'application');
}

dataset('forward pairs', [
    'admin to HOD' => ['admin', ForwardTarget::Hod],
    'admin to Dean' => ['admin', ForwardTarget::Dean],
    'admin to Registrar' => ['admin', ForwardTarget::Registrar],
    'HOD to admin' => ['hod', ForwardTarget::AdminOfficer],
    'HOD to Dean' => ['hod', ForwardTarget::Dean],
    'HOD to Registrar' => ['hod', ForwardTarget::Registrar],
    'Dean to admin' => ['dean', ForwardTarget::AdminOfficer],
    'Dean to HOD' => ['dean', ForwardTarget::Hod],
    'Dean to Registrar' => ['dean', ForwardTarget::Registrar],
]);

test('every allowed pair can forward and then exchange messages in its own private conversation', function (string $sender, ForwardTarget $target) {
    Notification::fake();
    $staff = flexibleStaff();
    $from = $staff[$sender];

    expect($from->can('forward', [$staff['application'], $target]))->toBeTrue();

    $forward = app(ForwardApplication::class)->handle($staff['application'], $from, $target, 'Please look at this.', 'key-'.$sender.$target->value);
    $conversation = $forward->conversation;

    expect($conversation->party_a->order() < $conversation->party_b->order())->toBeTrue()
        ->and($conversation->involves(CorrespondenceParty::forRole($from->staffRole())))->toBeTrue()
        ->and($conversation->involves(CorrespondenceParty::fromForwardTarget($target)))->toBeTrue()
        ->and($from->can('view', $conversation))->toBeTrue();

    if ($target === ForwardTarget::Registrar) {
        expect($conversation->isWithRegistrar())->toBeTrue()
            ->and($from->can('reply', $conversation))->toBeFalse()
            ->and($forward->to_email)->toBe('registrar@superior.edu.pk');

        return;
    }

    $recipient = User::query()->findOrFail($forward->to_user_id);
    expect($recipient->can('reply', $conversation))->toBeTrue();

    $reply = app(ReplyToCorrespondence::class)->handle($conversation, $recipient, 'Noted, thank you.', 'reply-'.$sender.$target->value);

    expect($reply->conversation_id)->toBe($conversation->id)
        ->and($reply->to_user_id)->toBe($from->id);
})->with('forward pairs');

test('nobody can forward to themselves, by policy or by the action', function (string $sender, ForwardTarget $self) {
    $staff = flexibleStaff();
    $from = $staff[$sender];

    expect($from->can('forward', [$staff['application'], $self]))->toBeFalse();

    Livewire::actingAs($from)
        ->test($sender === 'admin' ? Show::class : App\Livewire\Reviewer\Applications\Show::class, ['application' => $staff['application']])
        ->set('forward_target', $self->value)
        ->set('forward_note', 'To myself.')
        ->call('forward')
        ->assertForbidden();

    expect(fn () => app(ForwardApplication::class)->handle($staff['application'], $from, $self, 'To myself.', 'self-key'))
        ->toThrow(ValidationException::class);
})->with([
    'admin' => ['admin', ForwardTarget::AdminOfficer],
    'HOD' => ['hod', ForwardTarget::Hod],
    'Dean' => ['dean', ForwardTarget::Dean],
]);

test('only the two participants read a conversation; the Admin Officer cannot read HOD, Dean or Registrar conversations between others', function () {
    $staff = flexibleStaff();
    $application = $staff['application'];
    recordForward($application, ForwardTarget::Dean, $staff['hod'], 'HD-SECRET', $staff['dean']);
    recordForward($application, ForwardTarget::Registrar, $staff['hod'], 'HR-SECRET');
    recordForward($application, ForwardTarget::Registrar, $staff['dean'], 'DR-SECRET');
    recordForward($application, ForwardTarget::Registrar, $staff['admin'], 'AR-SECRET');

    $between = fn (CorrespondenceParty $a, CorrespondenceParty $b) => CorrespondenceConversation::between($application->id, $a, $b);
    $hodDean = $between(CorrespondenceParty::Hod, CorrespondenceParty::Dean);
    $hodRegistrar = $between(CorrespondenceParty::Hod, CorrespondenceParty::Registrar);
    $deanRegistrar = $between(CorrespondenceParty::Dean, CorrespondenceParty::Registrar);
    $adminRegistrar = $between(CorrespondenceParty::AdminOfficer, CorrespondenceParty::Registrar);

    foreach ([$hodDean, $hodRegistrar, $deanRegistrar] as $conversation) {
        expect($staff['admin']->can('view', $conversation))->toBeFalse();
    }

    expect($staff['admin']->can('view', $adminRegistrar))->toBeTrue()
        ->and($staff['hod']->can('view', $adminRegistrar))->toBeFalse()
        ->and($staff['dean']->can('view', $adminRegistrar))->toBeFalse()
        ->and($staff['hod']->can('view', $deanRegistrar))->toBeFalse()
        ->and($staff['dean']->can('view', $hodRegistrar))->toBeFalse();

    foreach ([$hodDean, $hodRegistrar, $deanRegistrar, $adminRegistrar] as $conversation) {
        expect(createSuperAdmin()->can('view', $conversation))->toBeFalse()
            ->and($application->student->user->can('view', $conversation))->toBeFalse();
    }

    $adminPanel = Livewire::actingAs($staff['admin'])->test(Thread::class, ['application' => $application]);
    foreach (['HD-SECRET', 'HR-SECRET', 'DR-SECRET'] as $secret) {
        $adminPanel->assertDontSee($secret);
    }

    Livewire::actingAs($staff['admin'])->test(Thread::class, ['application' => $application])
        ->call('selectConversation', $hodRegistrar->id)->assertNotFound();
});

test('the Admin Officer sees a neutral line for every forward between the HOD, Dean and Registrar, never its content', function () {
    $staff = flexibleStaff();
    $application = $staff['application'];
    recordForward($application, ForwardTarget::Dean, $staff['hod'], 'HD-SECRET', $staff['dean']);
    recordForward($application, ForwardTarget::Registrar, $staff['hod'], 'HR-SECRET');
    recordForward($application, ForwardTarget::Registrar, $staff['dean'], 'DR-SECRET');
    recordForward($application, ForwardTarget::Hod, $staff['dean'], 'DH-SECRET', $staff['hod']);

    Livewire::actingAs($staff['admin'])
        ->test(Thread::class, ['application' => $application])
        ->assertSee('HOD forwarded this to the Dean on')
        ->assertSee('HOD forwarded this to the Registrar on')
        ->assertSee('Dean forwarded this to the Registrar on')
        ->assertSee('Dean forwarded this to the HOD on')
        ->assertDontSee('HD-SECRET')
        ->assertDontSee('HR-SECRET')
        ->assertDontSee('DR-SECRET')
        ->assertDontSee('DH-SECRET');

    Livewire::actingAs($staff['hod'])
        ->test(Thread::class, ['application' => $application])
        ->assertDontSee('forwarded this to the');
});

test('an Admin Officer to Registrar forward is emailed to the forwarding.registrar_email setting', function () {
    fakeGmailApi();
    config(['mail.always_to' => null]);
    Setting::set(ForwardApplication::REGISTRAR_EMAIL_SETTING, 'registrar.inbox@example.test');
    $staff = flexibleStaff();
    connectGmail($staff['admin']);

    app(ForwardApplication::class)->handle($staff['application'], $staff['admin'], ForwardTarget::Registrar, 'Please process.', 'admin-registrar');

    $mail = Http::recorded(fn (Request $request) => str_starts_with($request->url(), 'https://gmail.googleapis.com/upload/'))
        ->map(fn (array $pair) => quoted_printable_decode($pair[0]->body()))
        ->first();

    expect($mail)->toContain('To: registrar.inbox@example.test', 'Dear registrar.inbox@example.test,');
});

test('the Admin Officer, HOD or Dean of a Registrar conversation can log the Registrar\'s emailed reply, nobody else', function (string $sender) {
    Notification::fake();
    $staff = flexibleStaff();
    $from = $staff[$sender];
    $forward = app(ForwardApplication::class)->handle($staff['application'], $from, ForwardTarget::Registrar, 'Please process.', 'reg-'.$sender);
    $conversation = $forward->conversation;

    expect($from->can('logExternalReply', $conversation))->toBeTrue();

    $others = array_diff(['admin', 'hod', 'dean'], [$sender]);
    foreach ($others as $other) {
        expect($staff[$other]->can('logExternalReply', $conversation))->toBeFalse();
    }
    expect(createSuperAdmin()->can('logExternalReply', $conversation))->toBeFalse();

    $logged = app(LogExternalReply::class)->handle($conversation, $from, 'Approved.', 'log-'.$sender);

    expect($logged->conversation_id)->toBe($conversation->id)
        ->and($logged->to_user_id)->toBe($from->id)
        ->and($logged->from_email)->toBe('registrar@superior.edu.pk');
})->with(['admin', 'hod', 'dean']);

test('a forward back to the Admin Officer does not tell the student or show on their timeline', function () {
    Notification::fake();
    $staff = flexibleStaff();

    app(ForwardApplication::class)->handle($staff['application'], $staff['dean'], ForwardTarget::AdminOfficer, 'Approved, please finish.', 'dean-admin');

    Notification::assertNotSentTo($staff['application']->student->user, ApplicationForwardedForReview::class);
    expect(ApplicationEvent::query()
        ->where('application_id', $staff['application']->id)
        ->where('event_type', ApplicationEventType::Forwarded)
        ->where('to_value', 'admin_officer')
        ->where('visible_to_student', true)
        ->exists())->toBeFalse()
        ->and($staff['application']->refresh()->forwarded_to)->toBeNull()
        ->and(CorrespondenceMessage::query()->where('to_party', CorrespondenceParty::AdminOfficer)->exists())->toBeTrue();
});

test('an HOD or Dean cannot forward an application they were never given, and an Admin Officer only acts in their own department', function () {
    $staff = flexibleStaff();
    $otherApplication = createApplicationInDepartment(Department::factory()->create(), createStudentUser());
    $otherAdmin = createAdminOfficer(Department::factory()->create());

    expect($staff['hod']->can('forward', [$otherApplication, ForwardTarget::Dean]))->toBeFalse()
        ->and($staff['dean']->can('forward', [$otherApplication, ForwardTarget::AdminOfficer]))->toBeFalse()
        ->and($otherAdmin->can('forward', [$staff['application'], ForwardTarget::Registrar]))->toBeFalse();
});
