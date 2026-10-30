<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcknowledgeAnnouncementRequest;
use App\Models\Announcement;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AnnouncementAcknowledgementController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $announcement = Announcement::nextPendingFor($request->user());

        if ($announcement === null) {
            return redirect()->route('student.dashboard');
        }

        return view('student.announcements.acknowledge', ['announcement' => $announcement]);
    }

    public function store(AcknowledgeAnnouncementRequest $request, Announcement $announcement): RedirectResponse
    {
        $acknowledgement = $announcement->acknowledgements()->firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'acknowledged_at' => now(),
                'survey_marked_done' => $request->boolean('survey_marked_done'),
            ],
        );

        if ($acknowledgement->wasRecentlyCreated) {
            activity()
                ->performedOn($announcement)
                ->causedBy($request->user())
                ->log('announcement acknowledged');
        }

        return redirect()->route('student.announcements.acknowledge');
    }
}
