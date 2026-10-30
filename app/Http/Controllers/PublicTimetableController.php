<?php

namespace App\Http\Controllers;

use App\Models\TimetableShareLink;
use App\Models\TimetableVersion;
use App\Support\Timetable\TimetableExplorer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * The public, read-only timetable of one department. It is stateless:
 * the link is re-checked on every request, so deactivating or replacing
 * the token takes effect at once.
 */
class PublicTimetableController extends Controller
{
    private const MODES = ['teacher', 'subject', 'room', 'section'];

    private const RESULT_LIMIT = 10;

    public function show(Request $request, string $token): Response
    {
        $link = TimetableShareLink::activeForToken($token);
        $version = $link === null ? null : TimetableVersion::publishedForDepartment($link->department_id);

        if ($link === null) {
            return $this->notFound();
        }

        $data = ['department' => $link->department->name, 'explorer' => null];

        if ($version !== null) {
            $explorer = new TimetableExplorer($version->classes()->with('sections:id,timetable_class_id,section')->get());
            $mode = in_array($request->query('mode'), self::MODES, true) ? $request->query('mode') : 'teacher';
            $term = mb_substr(TimetableExplorer::normalize($request->query('term')), 0, 100);
            $day = (int) $request->query('day');
            $day = in_array($day, $explorer->days(), true) ? $day : null;

            $matches = $term === '' ? null : match ($mode) {
                'subject' => $explorer->subjects($term, $day),
                'room' => $explorer->rooms($term, $day),
                'section' => $explorer->sections($term, $day),
                default => $explorer->teachers($term, $day),
            };

            $data = [...$data, ...[
                'explorer' => $explorer,
                'stats' => $explorer->stats(),
                'days' => $explorer->days(),
                'mode' => $mode,
                'term' => $term,
                'day' => $day,
                'items' => $matches?->take(self::RESULT_LIMIT) ?? new Collection,
                'total' => $matches?->count() ?? 0,
                'searched' => $matches !== null,
            ]];
        }

        return $this->withHeaders(response()->view('public.timetable', $data));
    }

    private function notFound(): Response
    {
        return $this->withHeaders(response()->view('public.not-found', [], 404));
    }

    private function withHeaders(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('Cache-Control', 'no-store, private');
    }
}
