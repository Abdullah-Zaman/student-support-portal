<?php

namespace App\Livewire\Admin\Applications;

use App\Concerns\ForwardsApplications;
use App\Enums\ApplicationEventType;
use App\Enums\ApplicationPriority;
use App\Enums\ApplicationStatus;
use App\Enums\MessageType;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\ApplicationMessage;
use App\Models\InternalNote;
use App\Notifications\AdminMessageReceived;
use App\Notifications\ApplicationClosed;
use App\Notifications\ApplicationInfoRequested;
use App\Notifications\ApplicationRejected;
use App\Notifications\ApplicationResolved;
use App\Notifications\ApplicationStatusChanged;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Application Details')]
class Show extends Component
{
    use ForwardsApplications;

    /**
     * Status values selectable from the generic "change status" action.
     * Resolve/reject/close are their own dedicated actions because they
     * require extra data (a note or reason) and set their own timestamp.
     */
    private const SELECTABLE_STATUSES = [
        'submitted',
        'under_review',
        'info_required',
        'in_progress',
    ];

    public const MESSAGE_MAX_LENGTH = 1000;

    public Application $application;

    public string $priority_input = '';

    public string $status_input = '';

    public string $resolution_note = '';

    public string $rejection_reason = '';

    public string $new_note = '';

    public ?int $editing_note_id = null;

    public string $editing_note_body = '';

    public string $message_body = '';

    public string $request_body = '';

    public function mount(Application $application): void
    {
        $this->authorize('view', $application);

        $this->application = $application->load(['category', 'attachments', 'student.user']);
        $this->priority_input = $application->priority->value;

        // The admin is now looking at this application, so any unread
        // student responses are no longer "new" for the list/dashboard.
        $this->application->unreadStudentResponses()->update(['read_at' => now()]);
    }

    /**
     * A single chronological feed of this application's status/priority
     * history and its messages (free-text, requests, responses) — each
     * message appears exactly once here, never duplicated as a separate
     * application_event.
     *
     * @return Collection<int, ApplicationEvent|ApplicationMessage>
     */
    #[Computed]
    public function timeline(): Collection
    {
        $events = $this->application->events()->with('user')->get();
        $messages = $this->application->messages()->with(['sender', 'attachments'])->get();

        return $events->concat($messages)->sortByDesc('created_at')->values();
    }

    /**
     * @return EloquentCollection<int, InternalNote>
     */
    #[Computed]
    public function internalNotes(): EloquentCollection
    {
        return $this->application->internalNotes()->with('admin')->latest()->get();
    }

    /**
     * @return EloquentCollection<int, Application>
     */
    #[Computed]
    public function previousApplications(): EloquentCollection
    {
        return Application::query()
            ->where('student_id', $this->application->student_id)
            ->where('id', '!=', $this->application->id)
            ->with('category')
            ->latest()
            ->get();
    }

    #[Computed]
    public function isFinal(): bool
    {
        return in_array($this->application->status, [ApplicationStatus::Closed, ApplicationStatus::Rejected], true);
    }

    #[Computed]
    public function isResolved(): bool
    {
        return $this->application->status === ApplicationStatus::Resolved;
    }

    #[Computed]
    public function hasOpenRequest(): bool
    {
        return $this->openRequestQuery()->exists();
    }

    protected function afterForwarding(): void
    {
        unset($this->timeline);
    }

    public function messageMaxLength(): int
    {
        return self::MESSAGE_MAX_LENGTH;
    }

    public function updatePriority(): void
    {
        $this->authorize('updatePriority', $this->application);
        $this->guardMutable();

        $validated = $this->validate([
            'priority_input' => ['required', Rule::in(array_column(ApplicationPriority::cases(), 'value'))],
        ])['priority_input'];

        if ($validated === $this->application->priority->value) {
            return;
        }

        $from = $this->application->priority->value;

        $this->application->update(['priority' => $validated]);

        ApplicationEvent::create([
            'application_id' => $this->application->id,
            'user_id' => Auth::id(),
            'event_type' => ApplicationEventType::PriorityChanged,
            'from_value' => $from,
            'to_value' => $validated,
            'visible_to_student' => false,
        ]);

        unset($this->timeline);

        $this->modal('confirm-update-priority')->close();
        Flux::toast(variant: 'success', text: __('Priority updated.'));
    }

    public function updateStatus(): void
    {
        $this->authorize('updateStatus', $this->application);
        $this->guardMutable();

        $validated = $this->validate([
            'status_input' => ['required', Rule::in(self::SELECTABLE_STATUSES)],
        ])['status_input'];

        if ($validated === $this->application->status->value) {
            return;
        }

        $this->cancelOpenRequestIfAny();

        $from = $this->application->status->value;

        $this->application->update(['status' => $validated]);

        ApplicationEvent::create([
            'application_id' => $this->application->id,
            'user_id' => Auth::id(),
            'event_type' => ApplicationEventType::StatusChanged,
            'from_value' => $from,
            'to_value' => $validated,
            'visible_to_student' => true,
        ]);

        unset($this->timeline, $this->hasOpenRequest);

        $this->application->student->user->notify(new ApplicationStatusChanged($this->application, $this->application->status));

        $this->modal('confirm-update-status')->close();
        Flux::toast(variant: 'success', text: __('Status updated.'));
    }

    public function resolve(): void
    {
        $this->authorize('resolve', $this->application);
        $this->guardMutable();

        $validated = $this->validate([
            'resolution_note' => ['required', 'string', 'max:1000'],
        ])['resolution_note'];

        $this->cancelOpenRequestIfAny();

        $from = $this->application->status->value;

        $this->application->update([
            'status' => ApplicationStatus::Resolved,
            'resolution_note' => $validated,
            'resolved_at' => now(),
        ]);

        ApplicationEvent::create([
            'application_id' => $this->application->id,
            'user_id' => Auth::id(),
            'event_type' => ApplicationEventType::Resolved,
            'from_value' => $from,
            'to_value' => ApplicationStatus::Resolved->value,
            'visible_to_student' => true,
        ]);

        unset($this->timeline, $this->hasOpenRequest);

        $this->application->student->user->notify(new ApplicationResolved($this->application, $validated));

        $this->modal('confirm-resolve')->close();
        Flux::toast(variant: 'success', text: __('Application marked resolved.'));
    }

    public function reject(): void
    {
        $this->authorize('reject', $this->application);
        $this->guardMutable();

        $validated = $this->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ])['rejection_reason'];

        $this->cancelOpenRequestIfAny();

        $from = $this->application->status->value;

        $this->application->update([
            'status' => ApplicationStatus::Rejected,
            'rejection_reason' => $validated,
        ]);

        ApplicationEvent::create([
            'application_id' => $this->application->id,
            'user_id' => Auth::id(),
            'event_type' => ApplicationEventType::Rejected,
            'from_value' => $from,
            'to_value' => ApplicationStatus::Rejected->value,
            'visible_to_student' => true,
        ]);

        unset($this->timeline, $this->hasOpenRequest);

        $this->application->student->user->notify(new ApplicationRejected($this->application, $validated));

        $this->modal('confirm-reject')->close();
        Flux::toast(variant: 'success', text: __('Application rejected.'));
    }

    public function close(): void
    {
        $this->authorize('close', $this->application);
        $this->guardNotFinal();

        $closingMessage = trim($this->message_body);

        if ($closingMessage !== '') {
            $closingMessage = $this->validate([
                'message_body' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
            ])['message_body'];
        }

        $this->cancelOpenRequestIfAny();

        $from = $this->application->status->value;

        $this->application->update([
            'status' => ApplicationStatus::Closed,
            'closed_at' => now(),
        ]);

        if ($closingMessage !== '') {
            ApplicationMessage::create([
                'application_id' => $this->application->id,
                'sender_id' => Auth::id(),
                'type' => MessageType::Message,
                'body' => $closingMessage,
            ]);

            $this->message_body = '';
        }

        ApplicationEvent::create([
            'application_id' => $this->application->id,
            'user_id' => Auth::id(),
            'event_type' => ApplicationEventType::Closed,
            'from_value' => $from,
            'to_value' => ApplicationStatus::Closed->value,
            'visible_to_student' => true,
        ]);

        unset($this->timeline, $this->hasOpenRequest);

        $this->application->student->user->notify(new ApplicationClosed($this->application, $closingMessage ?: null));

        $this->modal('confirm-close')->close();
        Flux::toast(variant: 'success', text: __('Application closed.'));
    }

    public function sendMessage(): void
    {
        $this->authorize('sendMessage', $this->application);
        $this->guardNotFinal();

        $validated = $this->validate([
            'message_body' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
        ])['message_body'];

        ApplicationMessage::create([
            'application_id' => $this->application->id,
            'sender_id' => Auth::id(),
            'type' => MessageType::Message,
            'body' => $validated,
        ]);

        $this->message_body = '';
        unset($this->timeline);

        $this->application->student->user->notify(new AdminMessageReceived($this->application, $validated));

        Flux::toast(variant: 'success', text: __('Message sent.'));
    }

    public function sendInfoRequest(): void
    {
        $this->createRequest(MessageType::InfoRequest, 'confirm-info-request', __('Info request sent.'));
    }

    public function sendDocumentRequest(): void
    {
        $this->createRequest(MessageType::DocumentRequest, 'confirm-document-request', __('Document request sent.'));
    }

    private function createRequest(MessageType $type, string $modalName, string $successMessage): void
    {
        $this->authorize('sendRequest', $this->application);
        $this->guardNotFinal();

        if ($this->hasOpenRequest()) {
            $this->addError('request_body', __('There is already an open request waiting for the student\'s response.'));

            return;
        }

        $validated = $this->validate([
            'request_body' => ['required', 'string', 'max:'.self::MESSAGE_MAX_LENGTH],
        ])['request_body'];

        ApplicationMessage::create([
            'application_id' => $this->application->id,
            'sender_id' => Auth::id(),
            'type' => $type,
            'body' => $validated,
        ]);

        $this->application->update(['status' => ApplicationStatus::InfoRequired]);

        $this->request_body = '';
        unset($this->timeline, $this->hasOpenRequest);

        $this->application->student->user->notify(new ApplicationInfoRequested($this->application, $type, $validated));

        $this->modal($modalName)->close();
        Flux::toast(variant: 'success', text: $successMessage);
    }

    public function addNote(): void
    {
        $this->authorize('create', [InternalNote::class, $this->application]);

        $validated = $this->validate([
            'new_note' => ['required', 'string', 'max:2000'],
        ])['new_note'];

        InternalNote::create([
            'application_id' => $this->application->id,
            'admin_id' => Auth::id(),
            'body' => $validated,
        ]);

        $this->new_note = '';
        unset($this->internalNotes);

        Flux::toast(variant: 'success', text: __('Note added.'));
    }

    public function startEditingNote(int $noteId): void
    {
        $note = InternalNote::findOrFail($noteId);
        $this->authorize('update', $note);

        $this->editing_note_id = $noteId;
        $this->editing_note_body = $note->body;
    }

    public function cancelEditingNote(): void
    {
        $this->editing_note_id = null;
        $this->editing_note_body = '';
    }

    public function saveNote(): void
    {
        $note = InternalNote::findOrFail($this->editing_note_id);
        $this->authorize('update', $note);

        $validated = $this->validate([
            'editing_note_body' => ['required', 'string', 'max:2000'],
        ])['editing_note_body'];

        $note->update(['body' => $validated]);

        $this->editing_note_id = null;
        $this->editing_note_body = '';
        unset($this->internalNotes);

        Flux::toast(variant: 'success', text: __('Note updated.'));
    }

    /**
     * Resolved, closed and rejected are all off-limits to priority/status
     * changes, resolving again, or rejecting — only close() may still run,
     * and only while not yet closed/rejected (see guardNotFinal()).
     */
    private function guardMutable(): void
    {
        abort_if(
            in_array($this->application->status, [ApplicationStatus::Resolved, ApplicationStatus::Closed, ApplicationStatus::Rejected], true),
            403,
        );
    }

    private function guardNotFinal(): void
    {
        abort_if($this->isFinal(), 403);
    }

    /**
     * @return HasMany<ApplicationMessage, Application>
     */
    private function openRequestQuery(): HasMany
    {
        return $this->application->messages()
            ->whereIn('type', [MessageType::InfoRequest->value, MessageType::DocumentRequest->value])
            ->whereNull('responded_at')
            ->whereNull('cancelled_at');
    }

    /**
     * Called whenever the admin changes status, resolves, rejects or
     * closes the application: any still-open request is auto-cancelled,
     * since the student can no longer usefully respond to it.
     */
    private function cancelOpenRequestIfAny(): void
    {
        $this->openRequestQuery()->update(['cancelled_at' => now()]);
    }

    public function render(): View
    {
        return view('livewire.admin.applications.show');
    }
}
