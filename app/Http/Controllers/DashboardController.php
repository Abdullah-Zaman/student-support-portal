<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Send each role to its own dashboard. A user with no recognized role
     * (e.g. a newly self-registered account before a role is assigned)
     * falls back to the generic placeholder view.
     */
    public function __invoke(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        return match (true) {
            $user->hasRole(RoleName::SuperAdmin->value) => redirect()->route('super-admin.dashboard'),
            $user->hasRole(RoleName::AdminOfficer->value) => redirect()->route('admin.dashboard'),
            $user->hasRole(RoleName::Student->value) => redirect()->route('student.dashboard'),
            $user->hasRole(RoleName::Hod->value) => redirect()->route('hod.dashboard'),
            $user->hasRole(RoleName::Dean->value) => redirect()->route('dean.dashboard'),
            default => view('dashboard'),
        };
    }
}
