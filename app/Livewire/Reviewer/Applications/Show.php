<?php

namespace App\Livewire\Reviewer\Applications;

use App\Actions\Forwarding\AddRemark;
use App\Concerns\ForwardsApplications;
use App\Enums\RemarkType;
use App\Enums\RoleName;
use App\Models\Application;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Read-only application view for the HOD or Dean it was forwarded to:
 * student, application, attachments and the forward/remark chain. No
 * internal notes, no priority, no messages. They can add a remark and
 * forward it onward.
 */
#[Title('Application Details')]
class Show extends Component
{
    use ForwardsApplications;

    #[Locked]
    public Application $application;

    public string $remark_type = '';

    public string $remark_body = '';

    public function mount(Application $application): void
    {
        $this->authorize('view', $application);

        $this->application = $application->load(['category', 'attachments', 'department', 'student.user', 'student.program']);
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

        $addRemark->handle($this->application, Auth::user(), RemarkType::from($validated['remark_type']), $validated['remark_body']);

        $this->reset('remark_type', 'remark_body');
        unset($this->forwards);

        Flux::toast(variant: 'success', text: __('Remark added.'));
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
