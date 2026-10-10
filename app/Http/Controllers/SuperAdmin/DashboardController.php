<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'departments' => Department::query()->count(),
            'admin_officers' => User::query()->role(RoleName::AdminOfficer->value)->count(),
            'students' => Student::query()->count(),
            'applications' => Application::query()->count(),
        ];

        $recentActivity = ApplicationEvent::query()
            ->with('application')
            ->latest('created_at')
            ->take(5)
            ->get();

        $recentAnnouncements = Announcement::query()
            ->with('department')
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('super-admin.dashboard', [
            'stats' => $stats,
            'recentActivity' => $recentActivity,
            'recentAnnouncements' => $recentAnnouncements,
        ]);
    }
}
