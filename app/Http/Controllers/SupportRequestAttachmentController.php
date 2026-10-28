<?php

namespace App\Http\Controllers;

use App\Models\SupportRequestAttachment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SupportRequestAttachmentController extends Controller
{
    public function show(SupportRequestAttachment $attachment): StreamedResponse
    {
        Gate::authorize('view', $attachment);

        return Storage::disk('local')->download($attachment->file_path, $attachment->original_name);
    }
}
