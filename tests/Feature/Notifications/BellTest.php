<?php

use App\Livewire\Notifications\Bell;
use App\Models\Department;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('each role sees a link to its own notifications index', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $admin = createAdminOfficer($department);
    $superAdmin = createSuperAdmin();

    Livewire::actingAs($student)->test(Bell::class)
        ->assertSee(route('student.notifications.index'));

    Livewire::actingAs($admin)->test(Bell::class)
        ->assertSee(route('admin.notifications.index'));

    Livewire::actingAs($superAdmin)->test(Bell::class)
        ->assertSee(route('super-admin.notifications.index'));
});

test('a user cannot open or mark as read another user\'s notification via the bell', function () {
    $owner = createStudentUser();
    $other = createStudentUser();

    $notification = $owner->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test',
        'data' => ['message' => 'Hello', 'url' => '/'],
    ]);

    Livewire::actingAs($other)
        ->test(Bell::class)
        ->call('open', $notification->id)
        ->assertNotFound();
});
