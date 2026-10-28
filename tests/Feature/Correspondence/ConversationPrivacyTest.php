<?php

use App\Enums\CorrespondenceParty;
use App\Enums\ForwardTarget;
use App\Livewire\Correspondence\Inbox;
use App\Livewire\Correspondence\Thread;
use App\Models\Application;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\User;
use App\Support\Correspondence\CorrespondenceInbox;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

/**
 * One application with all three private conversations, each holding one
 * forward with its own secret text.
 *
 * @return array{admin: User, hod: User, dean: User, application: Application, adminHod: CorrespondenceConversation, hodDean: CorrespondenceConversation, adminDean: CorrespondenceConversation}
 */
function threeConversations(): array
{
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $dean = createDean($faculty);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    recordForward($application, ForwardTarget::Hod, $admin, 'SECRET-ADMIN-HOD', $hod);
    recordForward($application, ForwardTarget::Dean, $hod, 'SECRET-HOD-DEAN', $dean);
    recordForward($application, ForwardTarget::Dean, $admin, 'SECRET-ADMIN-DEAN', $dean);

    $conversation = fn (CorrespondenceParty $a, CorrespondenceParty $b) => CorrespondenceConversation::between($application->id, $a, $b);

    return [
        'admin' => $admin,
        'hod' => $hod,
        'dean' => $dean,
        'application' => $application,
        'adminHod' => $conversation(CorrespondenceParty::AdminOfficer, CorrespondenceParty::Hod),
        'hodDean' => $conversation(CorrespondenceParty::Hod, CorrespondenceParty::Dean),
        'adminDean' => $conversation(CorrespondenceParty::AdminOfficer, CorrespondenceParty::Dean),
    ];
}

test('each participant sees only their own conversations and nobody else, not even the Super Admin', function () {
    $setup = threeConversations();
    $ids = fn (User $user) => CorrespondenceConversation::query()->visibleTo($user)->orderBy('id')->pluck('id')->all();
    $messageIds = fn (User $user) => CorrespondenceMessage::query()->visibleTo($user)->count();

    expect($ids($setup['admin']))->toBe([$setup['adminHod']->id, $setup['adminDean']->id])
        ->and($ids($setup['hod']))->toBe([$setup['adminHod']->id, $setup['hodDean']->id])
        ->and($ids($setup['dean']))->toBe([$setup['hodDean']->id, $setup['adminDean']->id])
        ->and($ids(createSuperAdmin()))->toBe([])
        ->and($ids($setup['application']->student->user))->toBe([])
        ->and($messageIds($setup['admin']))->toBe(2)
        ->and($messageIds(createSuperAdmin()))->toBe(0);

    expect($setup['admin']->can('view', $setup['hodDean']))->toBeFalse()
        ->and($setup['dean']->can('view', $setup['adminHod']))->toBeFalse()
        ->and($setup['hod']->can('view', $setup['adminDean']))->toBeFalse()
        ->and(createSuperAdmin()->can('view', $setup['adminHod']))->toBeFalse()
        ->and($setup['hod']->can('view', $setup['hodDean']))->toBeTrue();
});

test('staff of another department or faculty see none of the conversations', function () {
    $setup = threeConversations();
    $otherDepartment = Department::factory()->create();

    foreach ([createAdminOfficer($otherDepartment), createHod($otherDepartment), createDean()] as $outsider) {
        expect(CorrespondenceConversation::query()->visibleTo($outsider)->exists())->toBeFalse();
    }
});

test('the conversations panel shows each participant only their own chats', function (string $viewer, array $visible, array $hidden) {
    $setup = threeConversations();
    $secrets = ['adminHod' => 'SECRET-ADMIN-HOD', 'hodDean' => 'SECRET-HOD-DEAN', 'adminDean' => 'SECRET-ADMIN-DEAN'];

    $component = Livewire::actingAs($setup[$viewer])->test(Thread::class, ['application' => $setup['application']]);

    foreach ($visible as $key) {
        $component->call('selectConversation', $setup[$key]->id)->assertSee($secrets[$key]);
    }

    foreach ($hidden as $key) {
        $component->assertDontSee($secrets[$key])->call('selectConversation', $setup[$key]->id)->assertNotFound();
    }
})->with([
    'admin' => ['admin', ['adminHod', 'adminDean'], ['hodDean']],
    'hod' => ['hod', ['adminHod', 'hodDean'], ['adminDean']],
    'dean' => ['dean', ['hodDean', 'adminDean'], ['adminHod']],
]);

test('the Admin Officer sees a neutral line about a forward between others, without its content', function () {
    $setup = threeConversations();

    Livewire::actingAs($setup['admin'])
        ->test(Thread::class, ['application' => $setup['application']])
        ->assertSee('HOD forwarded this to the Dean on')
        ->assertDontSee('SECRET-HOD-DEAN');
});

test('the inbox lists only my conversations and counts unread per conversation', function () {
    $setup = threeConversations();

    Livewire::actingAs($setup['dean'])->test(Inbox::class)
        ->assertSee('SECRET-HOD-DEAN')
        ->assertSee('SECRET-ADMIN-DEAN')
        ->assertDontSee('SECRET-ADMIN-HOD')
        ->assertSeeHtml('data-test="inbox-unread-'.$setup['hodDean']->id.'"')
        ->assertSeeHtml('data-test="inbox-unread-'.$setup['adminDean']->id.'"');

    $inbox = app(CorrespondenceInbox::class);
    expect($inbox->unreadCount($setup['dean']))->toBe(2)
        ->and($inbox->unreadCount($setup['hod']))->toBe(1)
        ->and($inbox->unreadCount($setup['admin']))->toBe(0);

    Livewire::actingAs($setup['dean'])
        ->test(Thread::class, ['application' => $setup['application']])
        ->call('selectConversation', $setup['hodDean']->id)
        ->call('selectConversation', $setup['adminDean']->id);

    expect($inbox->unreadCount($setup['dean']))->toBe(0);
});

test('the Super Admin has no inbox and no conversation panel', function () {
    $setup = threeConversations();

    Livewire::actingAs(createSuperAdmin())->test(Inbox::class)->assertForbidden();
    Livewire::actingAs(createSuperAdmin())->test(Thread::class, ['application' => $setup['application']])->assertForbidden();
});

test('the audit log keeps only metadata of a message, never its text', function () {
    $setup = threeConversations();
    $activity = Activity::query()
        ->where('subject_type', (new CorrespondenceMessage)->getMorphClass())
        ->where('event', 'created')
        ->get();

    expect($activity)->not->toBeEmpty()
        ->and($activity->pluck('properties')->toJson())->not->toContain('SECRET-');
});
