<?php

namespace App\Livewire\Support;

use App\Models\SupportRequest;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * One of the reporter's own support requests, read-only: what they wrote,
 * its status and the Super Admin's note.
 */
#[Title('Support request')]
class Show extends Component
{
    #[Locked]
    public SupportRequest $supportRequest;

    public function mount(SupportRequest $supportRequest): void
    {
        $this->authorize('view', $supportRequest);

        $this->supportRequest = $supportRequest->load(['attachments', 'application', 'student.user']);
    }

    public function render(): View
    {
        return view('livewire.support.show');
    }
}
