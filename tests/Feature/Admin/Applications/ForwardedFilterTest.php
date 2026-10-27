<?php

use App\Actions\Forwarding\AddRemark;
use App\Enums\ForwardTarget;
use App\Enums\RemarkType;
use App\Livewire\Admin\Applications\Index;
use App\Livewire\Admin\Applications\Show;
use App\Models\Department;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
});

test('the applications list shows where each application was forwarded and filters by it', function (string $filter, array $visible, array $hidden) {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $student = createStudentUser($department);
    foreach (['Never forwarded' => null, 'Sent to the HOD' => ForwardTarget::Hod, 'Sent to the Dean' => ForwardTarget::Dean, 'Sent to the Registrar' => ForwardTarget::Registrar] as $subject => $target) {
        $application = createApplicationInDepartment($department, $student);
        $application->update(['subject' => $subject, 'forwarded_to' => $target]);
    }

    $component = Livewire::actingAs($admin)->test(Index::class)->set('forwarded', $filter);

    foreach ($visible as $subject) {
        $component->assertSee($subject);
    }
    foreach ($hidden as $subject) {
        $component->assertDontSee($subject);
    }
})->with([
    'all' => ['', ['Never forwarded', 'Sent to the HOD', 'With HOD', 'With Dean', 'Sent to Registrar'], []],
    'any forward' => ['any', ['Sent to the HOD', 'Sent to the Dean', 'Sent to the Registrar'], ['Never forwarded']],
    'HOD' => ['hod', ['Sent to the HOD'], ['Never forwarded', 'Sent to the Dean', 'Sent to the Registrar']],
    'Registrar' => ['registrar', ['Sent to the Registrar'], ['Never forwarded', 'Sent to the HOD', 'Sent to the Dean']],
]);

test('the admin application view shows the correspondence with remarks', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));
    recordForward($application, ForwardTarget::Hod, $admin, 'Please advise on the instalment plan.');
    app(AddRemark::class)->handle($application, $hod, RemarkType::Recommended, 'Supported by the department.', 'remark-key');

    Livewire::actingAs($admin)
        ->test(Show::class, ['application' => $application])
        ->assertSee('Please advise on the instalment plan.')
        ->assertSee('Recommended')
        ->assertSee('Supported by the department.')
        ->assertSee('With HOD');
});
