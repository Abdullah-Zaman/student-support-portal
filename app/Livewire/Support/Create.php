<?php

namespace App\Livewire\Support;

use App\Actions\Support\CreateSupportRequest;
use App\Enums\SupportRequestCategory;
use App\Models\Application;
use App\Models\Student;
use App\Models\SupportRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * "Contact Super Admin": an Admin Officer, HOD or Dean reports a problem.
 * They choose what to write and attach; nothing from their conversations
 * or applications is added automatically. A related application or
 * student can be named by number, but only one the reporter may open.
 */
#[Title('Contact Super Admin')]
class Create extends Component
{
    use WithFileUploads;

    public const ATTACHMENT_MAX_KILOBYTES = 5120;

    public const MAX_PER_HOUR = 5;

    public string $category = 'system_problem';

    public string $subject = '';

    public string $message = '';

    public string $application_no = '';

    public string $student_registration_no = '';

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $attachments = [];

    public function mount(): void
    {
        $this->authorize('create', SupportRequest::class);
    }

    public function removeAttachment(int $index): void
    {
        unset($this->attachments[$index]);
        $this->attachments = array_values($this->attachments);
    }

    public function save(CreateSupportRequest $createSupportRequest): void
    {
        $this->authorize('create', SupportRequest::class);

        $user = Auth::user();
        $throttleKey = 'support-request:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_PER_HOUR)) {
            throw ValidationException::withMessages([
                'subject' => __('You have sent several requests recently. Please try again in :minutes minutes.', [
                    'minutes' => max(1, (int) ceil(RateLimiter::availableIn($throttleKey) / 60)),
                ]),
            ]);
        }

        $validated = $this->validate([
            'category' => ['required', Rule::enum(SupportRequestCategory::class)],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:3000'],
            'application_no' => ['nullable', 'string', 'max:30'],
            'student_registration_no' => ['nullable', 'string', 'max:30'],
            'attachments' => ['nullable', 'array', 'max:3'],
            'attachments.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:'.self::ATTACHMENT_MAX_KILOBYTES],
        ], [
            'subject.required' => __('Enter a subject.'),
            'message.required' => __('Describe the problem.'),
            'attachments.max' => __('You can attach up to 3 files.'),
            'attachments.*.mimes' => __('Only images (JPG, PNG) and PDF files are allowed.'),
            'attachments.*.max' => __('Each file can be at most 5 MB.'),
        ]);

        $application = $this->relatedApplication($validated['application_no'] ?? null);
        $student = $this->relatedStudent($validated['student_registration_no'] ?? null);

        $createSupportRequest->handle($user, [
            'category' => $validated['category'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'application_id' => $application?->id,
            'student_id' => $student?->id,
        ], $this->attachments);

        RateLimiter::hit($throttleKey, 3600);

        Flux::toast(variant: 'success', text: __('Your request was sent to the Super Admin.'));

        $this->redirect(route('support.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.support.create');
    }

    /**
     * @throws ValidationException when the number is unknown or the reporter may not open it
     */
    private function relatedApplication(?string $number): ?Application
    {
        if ($number === null || trim($number) === '') {
            return null;
        }

        $application = Application::withoutGlobalScopes()->where('application_no', strtoupper(trim($number)))->first();

        if ($application === null || ! Gate::allows('view', $application)) {
            throw ValidationException::withMessages(['application_no' => __('No application with this number was found for you.')]);
        }

        return $application;
    }

    /**
     * @throws ValidationException when the number is unknown or the reporter may not open it
     */
    private function relatedStudent(?string $registrationNo): ?Student
    {
        if ($registrationNo === null || trim($registrationNo) === '') {
            return null;
        }

        $student = Student::query()->where('registration_no', strtoupper(trim($registrationNo)))->first();

        if ($student === null || ! Gate::allows('view', $student)) {
            throw ValidationException::withMessages(['student_registration_no' => __('No student with this registration number was found for you.')]);
        }

        return $student;
    }
}
