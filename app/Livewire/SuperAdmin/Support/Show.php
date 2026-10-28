<?php

namespace App\Livewire\SuperAdmin\Support;

use App\Actions\Support\UpdateSupportRequest;
use App\Models\SupportRequest;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One support request for the Super Admin: the message, attachments,
 * reporter and related application or student, with actions to set it in
 * progress, reply with a note, or resolve it (after a confirmation).
 */
#[Title('Support request')]
class Show extends Component
{
    #[Locked]
    public SupportRequest $supportRequest;

    public string $note = '';

    public function mount(SupportRequest $supportRequest): void
    {
        $this->authorize('manage', SupportRequest::class);
        $this->authorize('view', $supportRequest);

        $this->supportRequest = $supportRequest->load(['reporter', 'attachments', 'application', 'student.user', 'resolver']);
        $this->note = (string) $supportRequest->resolution_note;
    }

    public function markInProgress(UpdateSupportRequest $updateSupportRequest): void
    {
        $this->authorize('update', $this->supportRequest);

        $updateSupportRequest->markInProgress($this->supportRequest, Auth::user(), $this->note);

        Flux::toast(variant: 'success', text: __('Marked as in progress. The reporter was told.'));
    }

    public function reply(UpdateSupportRequest $updateSupportRequest): void
    {
        $this->authorize('update', $this->supportRequest);

        $validated = $this->validate(['note' => ['required', 'string', 'max:2000']], [
            'note.required' => __('Write a reply for the reporter.'),
        ]);

        $updateSupportRequest->reply($this->supportRequest, Auth::user(), $validated['note']);

        Flux::toast(variant: 'success', text: __('Reply saved. The reporter was told.'));
    }

    public function resolve(UpdateSupportRequest $updateSupportRequest): void
    {
        $this->authorize('update', $this->supportRequest);

        try {
            $validated = $this->validate(['note' => ['required', 'string', 'max:2000']], [
                'note.required' => __('Write a resolution note before resolving.'),
            ]);
        } catch (ValidationException $exception) {
            $this->modal('confirm-resolve')->close();

            throw $exception;
        }

        $updateSupportRequest->resolve($this->supportRequest, Auth::user(), $validated['note']);

        $this->modal('confirm-resolve')->close();
        Flux::toast(variant: 'success', text: __('Request resolved. The reporter was told.'));
    }

    public function render(): View
    {
        return view('livewire.super-admin.support.show');
    }
}
