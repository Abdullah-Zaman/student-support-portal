<?php

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationEvent;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\ApplicationCategorySeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    createRoles();
});

/**
 * Mimics the old, unrealistic seed: generic names, student{n}@ emails,
 * and the exact per-student application counts DemoSeeder::demoStudents()
 * expects (4,3,2,2,3,1,3,1,1,3), so the refresh command has something
 * real to rewrite.
 */
function seedUnrealisticDemoData(Department $department): void
{
    $counts = [4, 3, 2, 2, 3, 1, 3, 1, 1, 3];
    $category = ApplicationCategory::query()->where('department_id', $department->id)->first();

    foreach ($counts as $i => $count) {
        $user = User::factory()->create([
            'name' => "Generic Name {$i}",
            'email' => "student{$i}@superior.edu.pk",
            'department_id' => $department->id,
        ]);
        $user->assignRole(RoleName::Student->value);

        $student = Student::query()->create([
            'user_id' => $user->id,
            'registration_no' => "SU92-BSSEM-F22-{$i}99",
            'program' => 'BS Software Engineering',
            'current_semester' => 5,
            'batch' => 'F22',
        ]);

        for ($j = 0; $j < $count; $j++) {
            Application::factory()->create([
                'student_id' => $student->id,
                'department_id' => $department->id,
                'category_id' => $category->id,
                'subject' => 'Lorem ipsum placeholder subject',
            ]);
        }
    }
}

test('demo:refresh rewrites demo students and applications without touching the protected account', function () {
    $department = Department::factory()->create(['code' => 'SE']);
    $this->seed(ApplicationCategorySeeder::class);
    createAdminOfficer($department);

    seedUnrealisticDemoData($department);

    $protectedUser = User::factory()->create([
        'name' => 'Abdullah Zaman',
        'email' => 'su92-bssem-f22-171@superior.edu.pk',
        'department_id' => $department->id,
    ]);
    $protectedUser->assignRole(RoleName::Student->value);
    $protectedStudent = Student::query()->create([
        'user_id' => $protectedUser->id,
        'registration_no' => 'SU92-BSSEM-F22-171',
        'program' => 'BS Software Engineering',
        'current_semester' => 8,
        'batch' => 'F22',
    ]);
    $protectedApplication = Application::factory()->create([
        'student_id' => $protectedStudent->id,
        'department_id' => $department->id,
        'category_id' => ApplicationCategory::query()->where('department_id', $department->id)->first()->id,
        'subject' => 'Protected application must not change',
    ]);

    Artisan::call('demo:refresh');

    expect($protectedUser->refresh()->name)->toBe('Abdullah Zaman');
    expect($protectedUser->email)->toBe('su92-bssem-f22-171@superior.edu.pk');
    expect($protectedApplication->refresh()->subject)->toBe('Protected application must not change');

    $firstDemoStudent = Student::query()->where('registration_no', 'SU92-BSSEM-F22-101')->with('user')->first();
    expect($firstDemoStudent)->not->toBeNull();
    expect($firstDemoStudent->user->name)->toBe('Hamza Ali');
    expect($firstDemoStudent->user->email)->toBe('su92-bssem-f22-101@superior.edu.pk');

    $firstApplication = $firstDemoStudent->applications()->orderBy('id')->first();
    expect($firstApplication->subject)->toBe('Fee challan not updated after payment');
    expect($firstApplication->status)->toBe(ApplicationStatus::Resolved);
    expect($firstApplication->resolution_note)->not->toBeNull();

    // Resolved (concluded) — treated as submitted a semester earlier than current.
    expect($firstApplication->semester_at_submission)->toBe($firstDemoStudent->current_semester - 1);
    expect($firstApplication->created_at)->not->toBeNull();
    expect($firstApplication->created_at->lessThan(now()))->toBeTrue();

    $events = $firstApplication->events()->orderBy('created_at')->get();
    expect($events)->toHaveCount(2);
    expect($events[0]->event_type)->toBe(ApplicationEventType::Submitted);
    expect($events[1]->event_type)->toBe(ApplicationEventType::Resolved);
    expect($events[1]->from_value)->toBe('submitted');
    expect($events[1]->to_value)->toBe('resolved');

    // The protected application never had events, and refreshing other
    // applications must never create any for it.
    expect(ApplicationEvent::query()->where('application_id', $protectedApplication->id)->count())->toBe(0);
});

test('DemoSeeder creates 10 realistic students with 23 applications in total', function () {
    $department = Department::factory()->create(['code' => 'SE']);
    $this->seed(ApplicationCategorySeeder::class);
    createAdminOfficer($department);

    $this->seed(DemoSeeder::class);

    expect(Student::count())->toBe(10);
    expect(Application::count())->toBe(23);

    $hamza = Student::query()->where('registration_no', 'SU92-BSSEM-F22-101')->with('user')->first();
    expect($hamza->user->name)->toBe('Hamza Ali');
    expect($hamza->user->email)->toBe('su92-bssem-f22-101@superior.edu.pk');

    // Every application gets a "submitted" event, plus one more for each
    // that has already moved past "submitted" (19 of the 23 have).
    expect(ApplicationEvent::count())->toBe(23 + 19);
    expect(ApplicationEvent::where('event_type', ApplicationEventType::Submitted->value)->count())->toBe(23);
});

test('DemoSeeder creates message threads and three announcements covering every status', function () {
    $department = Department::factory()->create(['code' => 'SE']);
    $this->seed(ApplicationCategorySeeder::class);
    createAdminOfficer($department);

    $this->seed(DemoSeeder::class);

    $infoRequired = Application::query()->where('status', ApplicationStatus::InfoRequired)->first();
    expect($infoRequired)->not->toBeNull();
    expect($infoRequired->messages()->where('type', 'info_request')->whereNull('responded_at')->exists())->toBeTrue();

    $respondedThread = Application::query()->where('subject', 'Unable to submit assignment due to LMS error')->first();
    expect($respondedThread->messages()->where('type', 'student_response')->exists())->toBeTrue();
    expect($respondedThread->messages()->where('type', 'info_request')->whereNotNull('responded_at')->exists())->toBeTrue();

    expect(Announcement::count())->toBe(3);
    expect(Announcement::query()->where('title', 'like', '%Examination Schedule%')->exists())->toBeTrue();
});
