<?php

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\UserStatus;
use App\Livewire\Student\Applications\Create;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    createRoles();
    Storage::fake('local');
});

test('a student can submit an application without attachments', function () {
    $student = createStudentUser();
    $student->student->update(['current_semester' => 5]);
    $category = ApplicationCategory::factory()->create([
        'department_id' => $student->department_id,
        'is_active' => true,
    ]);

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'My fee challan is wrong')
        ->set('body', 'The amount on my fee challan does not match what I owe.')
        ->call('submit')
        ->assertHasNoErrors();

    $application = Application::query()->where('student_id', $student->student->id)->firstOrFail();

    expect($application->application_no)->toMatch('/^SC-\d{4}-\d{6}$/');
    expect($application->status)->toBe(ApplicationStatus::Submitted);
    expect($application->priority->value)->toBe('normal');
    expect($application->semester_at_submission)->toBe(5);
    expect($application->category_id)->toBe($category->id);

    $event = $application->events()->first();
    expect($event->event_type)->toBe(ApplicationEventType::Submitted);
    expect($event->visible_to_student)->toBeTrue();
});

test('a student can submit an application with attachments stored under a random filename', function () {
    $student = createStudentUser();
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);
    $file = UploadedFile::fake()->create('fee-challan.pdf', 500, 'application/pdf');

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Fee issue')
        ->set('body', 'Details about the fee issue.')
        ->set('attachments', [$file])
        ->call('submit')
        ->assertHasNoErrors();

    $application = Application::query()->where('student_id', $student->student->id)->firstOrFail();
    $attachment = $application->attachments()->firstOrFail();

    expect($attachment->original_name)->toBe('fee-challan.pdf');
    expect($attachment->file_path)->not->toContain('fee-challan');
    Storage::disk('local')->assertExists($attachment->file_path);
});

test('subject and body cannot exceed the configured max length', function () {
    Setting::set('applications.subject_max_length', '10');
    Setting::set('applications.body_max_length', '20');

    $student = createStudentUser();
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'This subject is way too long')
        ->set('body', 'This body is also far too long for the limit')
        ->call('submit')
        ->assertHasErrors(['subject', 'body']);
});

test('a file with a disallowed type is rejected', function () {
    $student = createStudentUser();
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);
    $file = UploadedFile::fake()->create('virus.exe', 100, 'application/x-msdownload');

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Subject')
        ->set('body', 'Body of the application.')
        ->set('attachments', [$file])
        ->call('submit')
        ->assertHasErrors(['attachments.0']);
});

test('a file larger than 5 mb shows its size in mb and is dropped from the list', function () {
    $student = createStudentUser();
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);
    $file = UploadedFile::fake()->create('fee-challan.pdf', 8800, 'application/pdf');

    // 8.6 MB clears the temp-upload stage's own ceiling (9 MB) intact, so
    // it reaches the component's submit-time validation and its message.
    $component = Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Subject')
        ->set('body', 'Body of the application.')
        ->set('attachments', [$file])
        ->call('submit')
        ->assertHasErrors(['attachments.0' => 'max'])
        ->assertSee('fee-challan.pdf is 8.6 MB. Maximum size is 5 MB.');

    expect($component->get('attachments'))->toBe([]);
});

test('a renamed text file shows a type message and is dropped from the list', function () {
    $student = createStudentUser();
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);
    $file = UploadedFile::fake()->create('resume.pdf', 10, 'text/plain');

    $component = Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Subject')
        ->set('body', 'Body of the application.')
        ->set('attachments', [$file])
        ->call('submit')
        ->assertHasErrors(['attachments.0' => 'mimes'])
        ->assertSee('resume.pdf: only PDF, JPG, PNG, DOC or DOCX files are allowed.');

    expect($component->get('attachments'))->toBe([]);
});

test('an invalid attachment is dropped while other valid attachments stay', function () {
    $student = createStudentUser();
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);
    $good = UploadedFile::fake()->create('good.pdf', 100, 'application/pdf');
    $bad = UploadedFile::fake()->create('bad.pdf', 8800, 'application/pdf');

    $component = Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Subject')
        ->set('body', 'Body of the application.')
        ->set('attachments', [$good, $bad])
        ->call('submit')
        ->assertHasErrors(['attachments.1' => 'max']);

    $remaining = $component->get('attachments');
    expect($remaining)->toHaveCount(1);
    expect($remaining[0]->getClientOriginalName())->toBe('good.pdf');
});

test('more than 3 attachments are rejected', function () {
    $student = createStudentUser();
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);
    $files = [
        UploadedFile::fake()->create('one.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('two.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('three.pdf', 100, 'application/pdf'),
        UploadedFile::fake()->create('four.pdf', 100, 'application/pdf'),
    ];

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $category->id)
        ->set('subject', 'Subject')
        ->set('body', 'Body of the application.')
        ->set('attachments', $files)
        ->call('submit')
        ->assertHasErrors(['attachments']);
});

test('a suspended student cannot submit an application', function () {
    $student = createStudentUser();
    $student->update(['status' => UserStatus::Suspended]);
    $category = ApplicationCategory::factory()->create(['department_id' => $student->department_id]);

    Livewire::actingAs($student)
        ->test(Create::class)
        ->assertForbidden();
});

test('an unverified student cannot reach the application form', function () {
    $student = createStudentUser();
    $student->update(['email_verified_at' => null]);

    $this->actingAs($student)->get(route('student.applications.create'))
        ->assertRedirect(route('verification.notice'));
});

test('a category from another department cannot be selected', function () {
    $student = createStudentUser();
    $otherCategory = ApplicationCategory::factory()->create();

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('category_id', (string) $otherCategory->id)
        ->set('subject', 'Subject')
        ->set('body', 'Body of the application.')
        ->call('submit')
        ->assertHasErrors(['category_id']);
});

test('a small PNG, JPG and PDF are all accepted as attachments', function () {
    $student = createStudentUser();

    Livewire::actingAs($student)
        ->test(Create::class)
        ->set('attachments', [
            UploadedFile::fake()->image('attendance_sample.png', 400, 300)->size(29),
            UploadedFile::fake()->image('photo.jpg', 400, 300)->size(15),
            UploadedFile::fake()->create('slip.pdf', 10, 'application/pdf'),
        ])
        ->assertHasNoErrors();
});

test('the upload failure message names the real reason instead of always saying too large', function () {
    $student = createStudentUser();

    $this->actingAs($student)
        ->get(route('student.applications.create'))
        ->assertOk()
        ->assertSee('is too large. Maximum size is 5 MB.')
        ->assertSee('is not an allowed type. Use PDF, JPG, PNG, DOC or DOCX.')
        ->assertSee('could not be uploaded because of a server or network problem.')
        ->assertDontSee('It may be too large.');
});

test('PHP has a writable temp directory, which uploads need before Laravel sees the file', function () {
    expect(is_writable(sys_get_temp_dir()))->toBeTrue(sys_get_temp_dir().' is not writable: uploads fail with "unable to create a temporary file". Set upload_tmp_dir in php.ini or TEMP/TMP in the environment that starts the server.');
});
