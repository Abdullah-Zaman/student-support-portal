<?php

namespace App\Http\Middleware;

use App\Models\Announcement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a student on the "Important announcement" page until they have
 * acknowledged every mandatory announcement of their department. Also a
 * Livewire persistent middleware, so direct update calls are blocked too.
 */
class RequireAnnouncementAcknowledgement
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && Announcement::nextPendingFor($user) !== null) {
            return redirect()->route('student.announcements.acknowledge');
        }

        return $next($request);
    }
}
