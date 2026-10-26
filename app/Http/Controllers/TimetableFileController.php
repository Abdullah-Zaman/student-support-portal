<?php

namespace App\Http\Controllers;

use App\Models\Scopes\AdminDepartmentScope;
use App\Models\TimetableVersion;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimetableFileController extends Controller
{
    /**
     * Loaded without the admin-department scope, so another department's
     * version is denied by the policy with a 403 instead of a 404.
     */
    public function download(int $version): StreamedResponse
    {
        $version = TimetableVersion::withoutGlobalScope(AdminDepartmentScope::class)->findOrFail($version);

        Gate::authorize('download', $version);

        return Storage::disk('local')->download($version->file_path, $version->original_name);
    }
}
