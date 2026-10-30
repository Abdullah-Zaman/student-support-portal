<?php

use App\Models\Announcement;
use App\Models\Department;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

function mandatoryAnnouncement(Department $department, array $attributes = []): Announcement
{
    return Announcement::factory()->create(array_merge([
        'department_id' => $department->id,
        'requires_acknowledgement' => true,
    ], $attributes));
}

test('a pending mandatory announcement redirects student pages to the acknowledgement page', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    mandatoryAnnouncement($department);

    $this->actingAs($student)->get(route('student.dashboard'))
        ->assertRedirect(route('student.announcements.acknowledge'));
    $this->actingAs($student)->get(route('student.applications.index'))
        ->assertRedirect(route('student.announcements.acknowledge'));
});

test('direct livewire update calls are blocked while an announcement is pending', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);

    $html = $this->actingAs($student)->get(route('student.notifications.index'))->assertOk()->getContent();
    preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
    $snapshot = html_entity_decode($matches[1], ENT_QUOTES);

    mandatoryAnnouncement($department);

    $this->actingAs($student)
        ->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
        ], ['X-Livewire' => '1'])
        ->assertRedirect(route('student.announcements.acknowledge'));
});

test('logout and the acknowledgement page stay reachable while blocked', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    mandatoryAnnouncement($department, ['title' => 'Fee survey', 'survey_url' => 'https://forms.example.com/x']);

    $this->actingAs($student)->get(route('student.announcements.acknowledge'))
        ->assertOk()
        ->assertSee('Fee survey')
        ->assertSee('https://forms.example.com/x');

    $this->actingAs($student)->post(route('logout'))->assertRedirect();
    $this->assertGuest();
});

test('acknowledging unlocks the portal and shows pending announcements one after another', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $first = mandatoryAnnouncement($department, ['title' => 'First notice']);
    $second = mandatoryAnnouncement($department, ['title' => 'Second notice']);

    $this->actingAs($student)->post(route('student.announcements.acknowledge.store', $first))
        ->assertRedirect(route('student.announcements.acknowledge'));
    $this->actingAs($student)->get(route('student.announcements.acknowledge'))->assertSee('Second notice');
    $this->actingAs($student)->get(route('student.dashboard'))->assertRedirect();

    $this->actingAs($student)->post(route('student.announcements.acknowledge.store', $second));
    $this->actingAs($student)->get(route('student.dashboard'))->assertOk();

    expect($first->acknowledgements()->where('user_id', $student->id)->exists())->toBeTrue();
    $this->assertDatabaseHas('activity_log', ['description' => 'announcement acknowledged', 'subject_id' => $first->id]);
});

test('a survey link requires the form to be marked as filled', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $announcement = mandatoryAnnouncement($department, ['survey_url' => 'https://forms.example.com/x']);

    $this->actingAs($student)->post(route('student.announcements.acknowledge.store', $announcement))
        ->assertSessionHasErrors('survey_marked_done');

    $this->actingAs($student)->post(route('student.announcements.acknowledge.store', $announcement), ['survey_marked_done' => '1']);
    $this->assertDatabaseHas('announcement_acknowledgements', [
        'announcement_id' => $announcement->id,
        'user_id' => $student->id,
        'survey_marked_done' => true,
    ]);
});

test('expired, inactive, scheduled and other-department announcements do not block', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);

    mandatoryAnnouncement($department, ['expires_at' => now()->subDay(), 'publish_at' => now()->subWeek()]);
    mandatoryAnnouncement($department, ['is_active' => false]);
    mandatoryAnnouncement($department, ['publish_at' => now()->addDay()]);
    mandatoryAnnouncement(Department::factory()->create());
    Announcement::factory()->create(['department_id' => $department->id]);

    $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
});

test('deactivating an announcement lifts the block', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $announcement = mandatoryAnnouncement($department);

    $this->actingAs($student)->get(route('student.dashboard'))->assertRedirect();

    $announcement->update(['is_active' => false]);

    $this->actingAs($student)->get(route('student.dashboard'))->assertOk();
});

test('a student cannot acknowledge an announcement of another department', function () {
    $student = createStudentUser();
    $foreign = mandatoryAnnouncement(Department::factory()->create());

    $this->actingAs($student)->post(route('student.announcements.acknowledge.store', $foreign))->assertForbidden();
    expect($foreign->acknowledgements()->count())->toBe(0);
});
