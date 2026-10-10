<?php

use App\Livewire\Admin\Announcements\Index;
use App\Models\Announcement;
use App\Models\Department;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
    Storage::fake('local');
});

test('an admin officer can create an announcement for their own department', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('title', 'Scholarship applications open')
        ->set('body', "Apply before 20 October.\nLate submissions will not be accepted.")
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::query()->where('title', 'Scholarship applications open')->firstOrFail();
    expect($announcement->department_id)->toBe($department->id);
    expect($announcement->created_by)->toBe($admin->id);
    expect($announcement->is_active)->toBeTrue();
});

test('creating and editing an announcement with files stores them and keeps the original attachment name', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    $image = UploadedFile::fake()->image('poster.jpg');
    $attachment = UploadedFile::fake()->create('form.pdf', 200, 'application/pdf');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('title', 'Event poster')
        ->set('body', 'Join us for the tech talk.')
        ->set('image', $image)
        ->set('attachment', $attachment)
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::query()->where('title', 'Event poster')->firstOrFail();
    expect($announcement->image_path)->not->toBeNull();
    expect($announcement->attachment_path)->not->toBeNull();
    expect($announcement->attachment_original_name)->toBe('form.pdf');
    Storage::disk('local')->assertExists($announcement->image_path);
    Storage::disk('local')->assertExists($announcement->attachment_path);
});

test('a super admin must choose a department when creating an announcement', function () {
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('title', 'Missing department')
        ->set('body', 'Body text.')
        ->call('save')
        ->assertHasErrors(['department_id' => 'required']);
});

test('a super admin can create an announcement for any chosen department', function () {
    $department = Department::factory()->create();
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($superAdmin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('department_id', (string) $department->id)
        ->set('title', 'Cross-department announcement')
        ->set('body', 'Body text.')
        ->call('save')
        ->assertHasNoErrors();

    $announcement = Announcement::query()->where('title', 'Cross-department announcement')->firstOrFail();
    expect($announcement->department_id)->toBe($department->id);
    expect($announcement->created_by)->toBe($superAdmin->id);
});

test('an admin officer cannot edit an announcement from another department', function () {
    $departmentA = Department::factory()->create();
    $departmentB = Department::factory()->create();
    $admin = createAdminOfficer($departmentA);
    $announcement = Announcement::factory()->create(['department_id' => $departmentB->id]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startEdit', $announcement->id)
        ->assertForbidden();
});

test('toggling active status flips it and is logged in the activity log', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $announcement = Announcement::factory()->create(['department_id' => $department->id, 'is_active' => true]);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('toggleActive', $announcement->id)
        ->assertHasNoErrors();

    expect($announcement->refresh()->is_active)->toBeFalse();

    $activity = Activity::query()
        ->where('subject_type', $announcement->getMorphClass())
        ->where('subject_id', $announcement->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->attribute_changes->get('attributes')['is_active'] ?? null)->toBeFalse();
});

test('the expiry date must be after the publish date', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('title', 'Bad dates')
        ->set('body', 'Body text.')
        ->set('publish_at', '2026-10-20T09:00')
        ->set('expires_at', '2026-10-10T09:00')
        ->call('save')
        ->assertHasErrors(['expires_at' => 'after']);
});

test('title and body are required and length-limited', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('title', '')
        ->set('body', '')
        ->call('save')
        ->assertHasErrors(['title' => 'required', 'body' => 'required']);

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('title', str_repeat('a', 151))
        ->set('body', str_repeat('a', 2001))
        ->call('save')
        ->assertHasErrors(['title' => 'max', 'body' => 'max']);
});

test('the image and attachment are restricted by type and size', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);

    $badImage = UploadedFile::fake()->create('poster.gif', 100, 'image/gif');
    $oversizedAttachment = UploadedFile::fake()->create('form.pdf', 6000, 'application/pdf');

    Livewire::actingAs($admin)
        ->test(Index::class)
        ->call('startCreate')
        ->set('title', 'Bad files')
        ->set('body', 'Body text.')
        ->set('image', $badImage)
        ->set('attachment', $oversizedAttachment)
        ->call('save')
        ->assertHasErrors(['image' => 'mimes', 'attachment' => 'max']);
});

test('the admin list shows the correct status label for each state', function () {
    expect(Announcement::factory()->make(['is_active' => false])->statusLabel())->toBe('Inactive');
    expect(Announcement::factory()->make(['is_active' => true, 'publish_at' => now()->addDay()])->statusLabel())->toBe('Scheduled');
    expect(Announcement::factory()->make(['is_active' => true, 'publish_at' => now()->subDay(), 'expires_at' => now()->subHour()])->statusLabel())->toBe('Expired');
    expect(Announcement::factory()->make(['is_active' => true, 'publish_at' => now()->subDay(), 'expires_at' => null])->statusLabel())->toBe('Live');
});
