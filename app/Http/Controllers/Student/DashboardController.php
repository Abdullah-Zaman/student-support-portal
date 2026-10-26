<?php

namespace App\Http\Controllers\Student;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Support\Timetable\StudentSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $student = Auth::user()->student;

        $applications = Application::query()
            ->where('student_id', $student->id)
            ->with('category')
            ->latest()
            ->take(4)
            ->get();

        $actionNeeded = Application::query()
            ->where('student_id', $student->id)
            ->where('status', ApplicationStatus::InfoRequired)
            ->with('category')
            ->latest()
            ->first();

        $latestEvents = ApplicationEvent::query()
            ->whereHas('application', fn ($query) => $query->where('student_id', $student->id))
            ->where('visible_to_student', true)
            ->with('application')
            ->latest('created_at')
            ->take(5)
            ->get();

        $stats = [
            'total' => Application::query()->where('student_id', $student->id)->count(),
            'waiting_on_you' => Application::query()->where('student_id', $student->id)->where('status', ApplicationStatus::InfoRequired)->count(),
            'processing' => Application::query()->where('student_id', $student->id)->whereIn('status', [ApplicationStatus::UnderReview, ApplicationStatus::InProgress])->count(),
            'resolved' => Application::query()->where('student_id', $student->id)->where('status', ApplicationStatus::Resolved)->count(),
        ];

        $announcements = Announcement::query()
            ->where('department_id', Auth::user()->department_id)
            ->visibleToStudents()
            ->orderByRaw('COALESCE(publish_at, created_at) desc')
            ->take(3)
            ->get();

        return view('student.dashboard', [
            'schedule' => StudentSchedule::for($student, Auth::user()->department_id),
            'applications' => $applications,
            'actionNeeded' => $actionNeeded,
            'latestEvents' => $latestEvents,
            'stats' => $stats,
            'announcements' => $announcements,
        ]);
    }
}
