<?php

use App\Enums\RoleName;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AnnouncementFileController;
use App\Http\Controllers\ApplicationAttachmentController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CorrespondenceAttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\TimetableFileController;
use App\Livewire\Admin\Announcements\Index as AdminAnnouncementsIndex;
use App\Livewire\Admin\Applications\Index as AdminApplicationsIndex;
use App\Livewire\Admin\Applications\Show as AdminApplicationsShow;
use App\Livewire\Admin\Students\Index as AdminStudentsIndex;
use App\Livewire\Admin\Students\Show as AdminStudentsShow;
use App\Livewire\Admin\Timetables\Index as AdminTimetablesIndex;
use App\Livewire\Admin\Timetables\Show as AdminTimetablesShow;
use App\Livewire\Correspondence\Inbox as CorrespondenceInbox;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Reviewer\Applications\Index as ReviewerApplicationsIndex;
use App\Livewire\Reviewer\Applications\Show as ReviewerApplicationsShow;
use App\Livewire\Reviewer\Dashboard as ReviewerDashboard;
use App\Livewire\Student\Announcements\Index as StudentAnnouncementsIndex;
use App\Livewire\Student\Applications\Create as ApplicationsCreate;
use App\Livewire\Student\Applications\Index as ApplicationsIndex;
use App\Livewire\Student\Applications\Show as ApplicationsShow;
use App\Livewire\Student\Profile as StudentProfile;
use App\Livewire\Student\Timetable as StudentTimetable;
use App\Livewire\SuperAdmin\AdminOfficers\Index as SuperAdminAdminOfficersIndex;
use App\Livewire\SuperAdmin\AuditLog\Index as SuperAdminAuditLogIndex;
use App\Livewire\SuperAdmin\Categories\Index as SuperAdminCategoriesIndex;
use App\Livewire\SuperAdmin\Departments\Index as SuperAdminDepartmentsIndex;
use App\Livewire\SuperAdmin\Faculties\Index as SuperAdminFacultiesIndex;
use App\Livewire\SuperAdmin\Programs\Index as SuperAdminProgramsIndex;
use App\Livewire\SuperAdmin\Reviewers\Index as SuperAdminReviewersIndex;
use App\Livewire\SuperAdmin\Settings\Edit as SuperAdminSettingsEdit;
use App\Livewire\SuperAdmin\Users\CreateTest as SuperAdminCreateTestUser;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

/*
 * Google OAuth for staff: sign in (guests) and connecting Gmail (staff;
 * the controller authorizes). Both come back through one callback URL.
 */
Route::get('auth/google/login', [GoogleAuthController::class, 'login'])
    ->middleware(['guest', 'throttle:10,1'])
    ->name('google.login');
Route::get('auth/google/connect', [GoogleAuthController::class, 'connect'])
    ->middleware(['auth', 'verified', 'throttle:10,1'])
    ->name('google.connect');
Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->middleware('throttle:10,1')
    ->name('google.callback');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('applications/attachments/{attachment}', [ApplicationAttachmentController::class, 'show'])
        ->name('applications.attachments.show');

    Route::get('correspondence/attachments/{attachment}', [CorrespondenceAttachmentController::class, 'show'])
        ->name('correspondence.attachments.show');

    Route::get('announcements/{announcement}/image', [AnnouncementFileController::class, 'image'])
        ->name('announcements.image.show');
    Route::get('announcements/{announcement}/attachment', [AnnouncementFileController::class, 'attachment'])
        ->name('announcements.attachment.show');

    Route::get('timetable/versions/{version}/download', [TimetableFileController::class, 'download'])
        ->name('timetable.download');
});

Route::middleware(['auth', 'verified', 'role:'.RoleName::Student->value])
    ->prefix('student')->name('student.')
    ->group(function () {
        Route::get('dashboard', StudentDashboardController::class)->name('dashboard');
        Route::livewire('profile', StudentProfile::class)->name('profile');

        Route::livewire('applications', ApplicationsIndex::class)->name('applications.index');
        Route::livewire('applications/create', ApplicationsCreate::class)->name('applications.create');
        Route::livewire('applications/{application}', ApplicationsShow::class)->name('applications.show');

        Route::livewire('notifications', NotificationsIndex::class)->name('notifications.index');
        Route::livewire('announcements', StudentAnnouncementsIndex::class)->name('announcements.index');
        Route::livewire('timetable', StudentTimetable::class)->name('timetable');
    });

Route::middleware(['auth', 'verified', 'role:'.RoleName::AdminOfficer->value])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        Route::livewire('applications', AdminApplicationsIndex::class)->name('applications.index');
        Route::livewire('applications/{application}', AdminApplicationsShow::class)->name('applications.show');
        Route::livewire('inbox', CorrespondenceInbox::class)->name('inbox');

        Route::livewire('students', AdminStudentsIndex::class)->name('students.index');
        Route::livewire('students/{student}', AdminStudentsShow::class)->name('students.show');

        Route::livewire('notifications', NotificationsIndex::class)->name('notifications.index');
        Route::livewire('announcements', AdminAnnouncementsIndex::class)->name('announcements.index');

        Route::livewire('timetable', AdminTimetablesIndex::class)->name('timetable.index');
        Route::livewire('timetable/{version}', AdminTimetablesShow::class)->name('timetable.show');
    });

Route::middleware(['auth', 'verified', 'role:'.RoleName::SuperAdmin->value])
    ->prefix('super-admin')->name('super-admin.')
    ->group(function () {
        Route::get('dashboard', SuperAdminDashboardController::class)->name('dashboard');

        Route::livewire('applications', AdminApplicationsIndex::class)->name('applications.index');
        Route::livewire('applications/{application}', AdminApplicationsShow::class)->name('applications.show');

        Route::livewire('admins', SuperAdminAdminOfficersIndex::class)->name('admins.index');
        Route::livewire('reviewers', SuperAdminReviewersIndex::class)->name('reviewers.index');
        Route::livewire('test-users/create', SuperAdminCreateTestUser::class)->name('test-users.create');

        Route::livewire('students', AdminStudentsIndex::class)->name('students.index');
        Route::livewire('students/{student}', AdminStudentsShow::class)->name('students.show');

        Route::livewire('categories', SuperAdminCategoriesIndex::class)->name('categories.index');

        Route::livewire('faculties', SuperAdminFacultiesIndex::class)->name('faculties.index');
        Route::livewire('departments', SuperAdminDepartmentsIndex::class)->name('departments.index');
        Route::livewire('programs', SuperAdminProgramsIndex::class)->name('programs.index');

        Route::livewire('notifications', NotificationsIndex::class)->name('notifications.index');
        Route::livewire('announcements', AdminAnnouncementsIndex::class)->name('announcements.index');

        Route::livewire('timetable', AdminTimetablesIndex::class)->name('timetable.index');
        Route::livewire('timetable/{version}', AdminTimetablesShow::class)->name('timetable.show');

        Route::livewire('audit-log', SuperAdminAuditLogIndex::class)->name('audit-log.index');
        Route::livewire('settings', SuperAdminSettingsEdit::class)->name('settings.edit');
    });

/*
 * HOD and Dean share one read-only portal under their own prefix. Which
 * applications they see is enforced by Application::forwardedTo and the
 * ApplicationPolicy, not by the route.
 */
foreach ([RoleName::Hod, RoleName::Dean] as $reviewerRole) {
    Route::middleware(['auth', 'verified', 'role:'.$reviewerRole->value])
        ->prefix($reviewerRole->value)->name($reviewerRole->value.'.')
        ->group(function () {
            Route::livewire('dashboard', ReviewerDashboard::class)->name('dashboard');
            Route::livewire('applications', ReviewerApplicationsIndex::class)->name('applications.index');
            Route::livewire('applications/{application}', ReviewerApplicationsShow::class)->name('applications.show');
            Route::livewire('inbox', CorrespondenceInbox::class)->name('inbox');
            Route::livewire('notifications', NotificationsIndex::class)->name('notifications.index');
        });
}

require __DIR__.'/settings.php';
