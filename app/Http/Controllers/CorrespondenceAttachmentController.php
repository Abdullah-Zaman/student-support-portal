<?php

namespace App\Http\Controllers;

use App\Models\CorrespondenceAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CorrespondenceAttachmentController extends Controller
{
    public function show(CorrespondenceAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
