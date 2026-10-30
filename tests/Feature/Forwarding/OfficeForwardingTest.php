<?php

use App\Enums\ForwardTarget;
use App\Livewire\Admin\Applications\Show as AdminShow;
use App\Livewire\Admin\Offices\Index;
use App\Livewire\Reviewer\Applications\Show as ReviewerShow;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Office;
use App\Models\OfficeContact;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    createRoles();
});

/**
 * @return array{admin: User, hod: User, dean: User, application: Application, office: Office, contact: OfficeContact}
 */
function officeSetup(): array
{
    $faculty = Faculty::factory()->create();
    $department = Department::factory()->create(['faculty_id' => $faculty->id]);
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $dean = createDean($faculty);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin, 'Please advise.', $hod);
    recordForward($application, ForwardTarget::Dean, $admin, 'For approval.', $dean);

    $office = Office::factory()->create(['department_id' => $department->id, 'name' => 'Accounts', 'available_to_hod' => true]);
    $contact = OfficeContact::factory()->create(['office_id' => $office->id, 'name' => 'Ali Raza', 'designation' => 'Accountant', 'email' => 'ali@superior.edu.pk']);

    return compact('admin', 'hod', 'dean', 'application', 'office', 'contact');
}

test('HOD and Dean see only the active offices switched on for them', function () {
    $s = officeSetup();
    Office::factory()->create(['department_id' => $s['office']->department_id, 'available_to_dean' => true, 'is_active' => false])
        ->contacts()->create(['name' => 'X', 'email' => 'x@superior.edu.pk']);

    $offerFor = fn (User $user, string $component) => Livewire::actingAs($user)
        ->test($component, ['application' => $s['application']])
        ->instance()->forwardOffices->pluck('id')->all();

    expect($offerFor($s['admin'], AdminShow::class))->toBe([$s['office']->id])
        ->and($offerFor($s['hod'], ReviewerShow::class))->toBe([$s['office']->id])
        ->and($offerFor($s['dean'], ReviewerShow::class))->toBe([]);

    $s['office']->update(['available_to_hod' => false]);

    expect($offerFor($s['hod'], ReviewerShow::class))->toBe([]);
});

test('an inactive contact or a hidden office cannot be chosen', function () {
    $s = officeSetup();
    $s['contact']->update(['is_active' => false]);

    Livewire::actingAs($s['admin'])
        ->test(AdminShow::class, ['application' => $s['application']])
        ->set('forward_target', 'office')
        ->set('forward_office_id', (string) $s['office']->id)
        ->set('forward_contact_id', (string) $s['contact']->id)
        ->set('forward_note', 'Please check.')
        ->call('forward')
        ->assertHasErrors();

    $s['contact']->update(['is_active' => true]);
    $s['office']->update(['available_to_hod' => false]);

    Livewire::actingAs($s['hod'])
        ->test(ReviewerShow::class, ['application' => $s['application']])
        ->set('forward_target', 'office')
        ->set('forward_office_id', (string) $s['office']->id)
        ->set('forward_contact_id', (string) $s['contact']->id)
        ->set('forward_note', 'Please check.')
        ->call('forward')
        ->assertHasErrors();

    expect(CorrespondenceMessage::query()->where('to_party', 'office')->count())->toBe(0);
});

test('an HOD forward goes from the HOD own Gmail and keeps a snapshot of the contact', function () {
    fakeGmailApi();
    $s = officeSetup();
    connectGmail($s['hod']);

    Livewire::actingAs($s['hod'])
        ->test(ReviewerShow::class, ['application' => $s['application']])
        ->set('forward_target', 'office')
        ->set('forward_office_id', (string) $s['office']->id)
        ->set('forward_contact_id', (string) $s['contact']->id)
        ->set('forward_note', 'Please confirm the fee.')
        ->call('forward')
        ->assertHasNoErrors();

    $message = CorrespondenceMessage::query()->where('to_party', 'office')->sole();
    expect($message->from_user_id)->toBe($s['hod']->id)
        ->and($message->to_email)->toBe('ali@superior.edu.pk')
        ->and($message->to_name)->toBe('Ali Raza')
        ->and($message->to_office_name)->toBe('Accounts');

    $raw = Http::recorded(fn (Request $request) => str_contains($request->url(), 'gmail.googleapis.com/upload'))->first()[0]->body();
    expect($raw)->toContain($s['hod']->email)->toContain('ali@superior.edu.pk');

    $s['contact']->update(['name' => 'Someone Else', 'email' => 'new@superior.edu.pk']);
    $s['office']->update(['name' => 'Renamed']);

    $message->refresh();
    expect($message->to_name)->toBe('Ali Raza')
        ->and($message->to_email)->toBe('ali@superior.edu.pk')
        ->and($message->to_office_name)->toBe('Accounts')
        ->and($s['application']->refresh()->forwarded_to)->toBe(ForwardTarget::Dean)
        ->and(Activity::query()->where('description', 'forwarded to office')->count())->toBe(1);
});

test('only the Admin Officer manages offices and contact emails are validated', function () {
    $s = officeSetup();

    Livewire::actingAs($s['hod'])->test(Index::class)->assertForbidden();
    Livewire::actingAs($s['dean'])->test(Index::class)->assertForbidden();

    $component = Livewire::actingAs($s['admin'])->test(Index::class);

    $component->call('startCreateContact', $s['office']->id)
        ->set('contact_name', 'Bad')
        ->set('contact_email', 'not-an-email')
        ->call('saveContact')
        ->assertHasErrors('contact_email');

    $component->set('contact_email', 'ALI@superior.edu.pk')->call('saveContact')->assertHasErrors('contact_email');

    $component->set('contact_email', 'fresh@superior.edu.pk')->call('saveContact')->assertHasNoErrors();
    expect($s['office']->contacts()->count())->toBe(2);

    $component->call('confirmToggle', 'office', $s['office']->id)->call('applyToggle');
    expect($s['office']->refresh()->is_active)->toBeFalse()
        ->and(Activity::query()->where('subject_type', Office::class)->exists())->toBeTrue();

    $otherOffice = Office::factory()->create();
    Livewire::actingAs($s['admin'])->test(Index::class)->call('startEditOffice', $otherOffice->id)->assertNotFound();
});
