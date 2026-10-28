<?php

use App\Actions\Correspondence\ReplyToCorrespondence;
use App\Enums\ForwardTarget;
use App\Livewire\Admin\Applications\Index as AdminApplicationsIndex;
use App\Livewire\Correspondence\Inbox;
use App\Livewire\Correspondence\Thread;
use App\Models\Department;
use App\Support\Correspondence\CorrespondenceInbox;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('a new message is unread for the recipient until they open the thread, then shows as seen to the sender', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $forward = recordForward($application, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    $forward->update(['seen_at' => null]);
    $inbox = app(CorrespondenceInbox::class);

    expect($inbox->unreadCount($hod))->toBe(1)
        ->and($inbox->unreadCount($admin))->toBe(0);

    $this->actingAs($hod)->get(route('hod.inbox'))->assertOk()->assertSee('1 unread');

    Livewire::actingAs($hod)->test(Thread::class, ['application' => $application]);

    expect($inbox->unreadCount($hod))->toBe(0)
        ->and($forward->refresh()->seen_by_id)->toBe($hod->id);

    Livewire::actingAs($admin)
        ->test(Thread::class, ['application' => $application])
        ->assertSeeHtml('data-test="seen-'.$forward->id.'"');
});

test('the sender opening the thread does not mark their own message as seen', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $forward = recordForward($application, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    $forward->update(['seen_at' => null]);

    Livewire::actingAs($admin)
        ->test(Thread::class, ['application' => $application])
        ->assertDontSeeHtml('data-test="seen-'.$forward->id.'"');

    expect($forward->refresh()->seen_at)->toBeNull();
});

test('the inbox lists unread threads first and filters by unread, awaiting my reply and awaiting their reply', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $student = createStudentUser($department);

    $awaitingHod = createApplicationInDepartment($department, $student);
    $awaitingHod->update(['subject' => 'Waiting on the HOD']);
    recordForward($awaitingHod, ForwardTarget::Hod, $admin, 'Please advise.', $hod)->update(['seen_at' => now()]);

    $hodReplied = createApplicationInDepartment($department, $student);
    $hodReplied->update(['subject' => 'HOD answered']);
    $forward = recordForward($hodReplied, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    app(ReplyToCorrespondence::class)->handle($forward->conversation, $hod, 'Approved.', 'reply-key');

    $notForwarded = createApplicationInDepartment($department, $student);
    $notForwarded->update(['subject' => 'Never forwarded']);

    $component = Livewire::actingAs($admin)->test(Inbox::class);
    $component->assertSeeInOrder(['HOD answered', 'Waiting on the HOD'])->assertDontSee('Never forwarded');

    $component->set('filter', 'unread')->assertSee('HOD answered')->assertDontSee('Waiting on the HOD');
    $component->set('filter', 'awaiting_me')->assertSee('HOD answered')->assertDontSee('Waiting on the HOD');
    $component->set('filter', 'awaiting_them')->assertSee('Waiting on the HOD')->assertDontSee('HOD answered');

    Livewire::actingAs($hod)->test(Inbox::class)
        ->set('filter', 'awaiting_me')->assertSee('Waiting on the HOD')->assertDontSee('HOD answered')
        ->set('filter', 'awaiting_them')->assertSee('HOD answered')->assertDontSee('Waiting on the HOD');
});

test('the sidebar shows the unread count, and the Super Admin and students have no inbox', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    $forward = recordForward($application, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    app(ReplyToCorrespondence::class)->handle($forward->conversation, $hod, 'Approved.', 'reply-key');

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['data-test="sidebar-inbox"', 'Inbox', '1'], false);

    Livewire::actingAs(createSuperAdmin())->test(Inbox::class)->assertForbidden();
    $this->actingAs($application->student->user)->get(route('admin.inbox'))->assertForbidden();
});

test('an HOD\'s inbox never shows another department\'s threads', function () {
    $department = Department::factory()->create();
    $otherDepartment = Department::factory()->create();
    $hod = createHod($department);
    $otherHod = createHod($otherDepartment);
    $application = createApplicationInDepartment($otherDepartment, createStudentUser($otherDepartment));
    $application->update(['subject' => 'Other department thread']);
    recordForward($application, ForwardTarget::Hod, createAdminOfficer($otherDepartment), 'Please advise.', $otherHod)->update(['seen_at' => null]);

    Livewire::actingAs($hod)->test(Inbox::class)->assertDontSee('Other department thread');
    expect(app(CorrespondenceInbox::class)->unreadCount($hod))->toBe(0);
});

test('the applications list shows where the Admin Officer\'s correspondence stands', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $student = createStudentUser($department);
    $waiting = createApplicationInDepartment($department, $student);
    recordForward($waiting, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    $answered = createApplicationInDepartment($department, $student);
    $forward = recordForward($answered, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    app(ReplyToCorrespondence::class)->handle($forward->conversation, $hod, 'Approved.', 'reply-key');

    Livewire::actingAs($admin)
        ->test(AdminApplicationsIndex::class)
        ->assertSee('With HOD · awaiting reply')
        ->assertSee('HOD replied');
});
