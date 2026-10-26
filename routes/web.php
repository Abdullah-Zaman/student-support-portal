<?php

use App\Enums\RoleName;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AnnouncementFileController;
use App\Http\Controllers\ApplicationAttachmentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Livewire\Admin\Announcements\Index as AdminAnnouncementsIndex;
use App\Livewire\Admin\Applications\Index as AdminApplicationsIndex;
use App\Livewire\Admin\Applications\Show as AdminApplicationsShow;
use App\Livewire\Admin\Students\Index as AdminStudentsIndex;
use App\Livewire\Admin\Students\Show as AdminStudentsShow;
use App\Livewire\Notifications\Index as NotificationsIndex;
use App\Livewire\Student\Announcements\Index as StudentAnnouncementsIndex;
use App\Livewire\Student\Applications\Create as ApplicationsCreate;
use App\Livewire\Student\Applications\Index as ApplicationsIndex;
use App\Livewire\Student\Applications\Show as ApplicationsShow;
use App\Livewire\Student\Profile as StudentProfile;
use App\Livewire\SuperAdmin\AdminOfficers\Index as SuperAdminAdminOfficersIndex;
use App\Livewire\SuperAdmin\AuditLog\Index as SuperAdminAuditLogIndex;
use App\Livewire\SuperAdmin\Categories\Index as SuperAdminCategoriesIndex;
use App\Livewire\SuperAdmin\Departments\Index as SuperAdminDepartmentsIndex;
use App\Livewire\SuperAdmin\Faculties\Index as SuperAdminFacultiesIndex;
use App\Livewire\SuperAdmin\Programs\Index as SuperAdminProgramsIndex;
use App\Livewire\SuperAdmin\Settings\Edit as SuperAdminSettingsEdit;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('applications/attachments/{attachment}', [ApplicationAttachmentController::class, 'show'])
        ->name('applications.attachments.show');

    Route::get('announcements/{announcement}/image', [AnnouncementFileController::class, 'image'])
        ->name('announcements.image.show');
    Route::get('announcements/{announcement}/attachment', [AnnouncementFileController::class, 'attachment'])
        ->name('announcements.attachment.show');
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
    });

Route::middleware(['auth', 'verified', 'role:'.RoleName::AdminOfficer->value])
    ->prefix('admin')->name('admin.')
    ->group(function () {
        Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

        Route::livewire('applications', AdminApplicationsIndex::class)->name('applications.index');
        Route::livewire('applications/{application}', AdminApplicationsShow::class)->name('applications.show');

        Route::livewire('students', AdminStudentsIndex::class)->name('students.index');
        Route::livewire('students/{student}', AdminStudentsShow::class)->name('students.show');

        Route::livewire('notifications', NotificationsIndex::class)->name('notifications.index');
        Route::livewire('announcements', AdminAnnouncementsIndex::class)->name('announcements.index');
    });

Route::middleware(['auth', 'verified', 'role:'.RoleName::SuperAdmin->value])
    ->prefix('super-admin')->name('super-admin.')
    ->group(function () {
        Route::get('dashboard', SuperAdminDashboardController::class)->name('dashboard');

        Route::livewire('applications', AdminApplicationsIndex::class)->name('applications.index');
        Route::livewire('applications/{application}', AdminApplicationsShow::class)->name('applications.show');

        Route::livewire('admins', SuperAdminAdminOfficersIndex::class)->name('admins.index');

        Route::livewire('students', AdminStudentsIndex::class)->name('students.index');
        Route::livewire('students/{student}', AdminStudentsShow::class)->name('students.show');

        Route::livewire('categories', SuperAdminCategoriesIndex::class)->name('categories.index');

        Route::livewire('faculties', SuperAdminFacultiesIndex::class)->name('faculties.index');
        Route::livewire('departments', SuperAdminDepartmentsIndex::class)->name('departments.index');
        Route::livewire('programs', SuperAdminProgramsIndex::class)->name('programs.index');

        Route::livewire('notifications', NotificationsIndex::class)->name('notifications.index');
        Route::livewire('announcements', AdminAnnouncementsIndex::class)->name('announcements.index');

        Route::livewire('audit-log', SuperAdminAuditLogIndex::class)->name('audit-log.index');
        Route::livewire('settings', SuperAdminSettingsEdit::class)->name('settings.edit');
    });

require __DIR__.'/settings.php';
