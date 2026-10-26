<?php

namespace App\Actions\Timetable;

use App\Enums\TimetableVersionStatus;
use App\Models\TimetableVersion;
use App\Models\User;
use App\Notifications\TimetablePublished;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Makes a version the one students of its department see: a draft is
 * published, an archived version is restored. The previously published
 * version is archived, and the department's students are notified.
 */
class PublishTimetableVersion
{
    public function handle(TimetableVersion $version, User $publisher): void
    {
        if ($version->status === TimetableVersionStatus::Published) {
            return;
        }

        DB::transaction(function () use ($version, $publisher) {
            TimetableVersion::withoutGlobalScopes()
                ->where('department_id', $version->department_id)
                ->where('status', TimetableVersionStatus::Published)
                ->lockForUpdate()
                ->get()
                ->each(fn (TimetableVersion $published) => $published->update(['status' => TimetableVersionStatus::Archived]));

            $version->update([
                'status' => TimetableVersionStatus::Published,
                'published_at' => now(),
                'published_by' => $publisher->id,
            ]);
        });

        Notification::send(User::activeStudentsInDepartment($version->department_id), new TimetablePublished($version));
    }
}
