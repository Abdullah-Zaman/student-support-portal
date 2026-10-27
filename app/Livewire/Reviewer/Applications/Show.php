<?php

namespace App\Livewire\Reviewer\Applications;

use App\Actions\Forwarding\AddRemark;
use App\Concerns\ForwardsApplications;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\RemarkType;
use App\Enums\RoleName;
use App\Models\Application;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Read-only application view for the HOD or Dean it was forwarded to:
 * student, application, attachments and the correspondence thread. No
 * internal notes, no priority, no student messages. They can add a
 * remark, reply in the thread and forward it onward.
 */
#[Title('Application Details')]
class Show extends Component
{
    use ForwardsApplications;

    #[Locked]
    public Application $application;

    public string $remark_type = '';

    public string $remark_body = '';

    /**
     * Idempotency key of the remark being written, so a double click
     * saves (and emails) it only once.
     */
    #[Locked]
    public string $remark_key = '';

    public function mount(Application $application): void
    {
        $this->authorize('view', $application);

        $this->application = $application->load(['category', 'attachments', 'department', 'student.user', 'student.program']);
        $this->remark_key = (string) Str::uuid();
    }

    public function addRemark(AddRemark $addRemark): void
    {
        $this->authorize('remark', $this->application);

        $validated = $this->validate([
            'remark_type' => ['required', Rule::enum(RemarkType::class)],
            'remark_body' => ['nullable', 'string', 'max:1000', Rule::requiredIf($this->remark_type === RemarkType::Comment->value)],
        ], [
            'remark_type.required' => __('Choose Recommended, Not recommended or Comment.'),
            'remark_body.required' => __('Write your comment.'),
        ]);

        $remark = $addRemark->handle($this->application, Auth::user(), RemarkType::from($validated['remark_type']), $validated['remark_body'], $this->remark_key);

        $this->reset('remark_type', 'remark_body');
        $this->remark_key = (string) Str::uuid();
        $this->dispatch('correspondence-posted');

        Flux::toast(variant: 'success', text: $remark->email_status === CorrespondenceEmailStatus::AwaitingGmail
            ? __('Remark saved. It will be emailed once you connect your Gmail.')
            : __('Remark added.'));
    }

    public function routePrefix(): string
    {
        return Auth::user()->hasRole(RoleName::Dean->value) ? 'dean.' : 'hod.';
    }

    public function render(): View
    {
        return view('livewire.reviewer.applications.show');
    }
}
