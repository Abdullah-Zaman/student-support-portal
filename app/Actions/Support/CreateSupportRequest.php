<?php

namespace App\Actions\Support;

use App\Enums\RoleName;
use App\Enums\SupportRequestCategory;
use App\Enums\SupportRequestStatus;
use App\Models\SupportRequest;
use App\Models\User;
use App\Notifications\SupportRequestCreated;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Records a support request from an Admin Officer, HOD or Dean, stores
 * its files on the private disk under random names, logs it, and tells
 * the Super Admins. The caller authorizes and validates first.
 */
class CreateSupportRequest
{
    /**
     * @param  array{category: string, subject: string, message: string, application_id?: int|null, student_id?: int|null}  $data
     * @param  array<int, UploadedFile>  $files
     */
    public function handle(User $reporter, array $data, array $files = []): SupportRequest
    {
        $supportRequest = DB::transaction(function () use ($reporter, $data, $files): SupportRequest {
            $supportRequest = SupportRequest::create([
                'reporter_id' => $reporter->id,
                'category' => SupportRequestCategory::from($data['category']),
                'subject' => $data['subject'],
                'message' => $data['message'],
                'application_id' => $data['application_id'] ?? null,
                'student_id' => $data['student_id'] ?? null,
                'status' => SupportRequestStatus::Open,
            ]);

            foreach ($files as $file) {
                $supportRequest->attachments()->create([
                    'original_name' => $file->getClientOriginalName(),
                    'file_path' => $file->store('support-requests/'.$supportRequest->id, 'local'),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                ]);
            }

            activity()
                ->causedBy($reporter)
                ->performedOn($supportRequest)
                ->withProperties(['category' => $supportRequest->category->value])
                ->log('support request created');

            return $supportRequest;
        });

        Notification::send(User::query()->role(RoleName::SuperAdmin->value)->get(), new SupportRequestCreated($supportRequest));

        return $supportRequest;
    }
}
