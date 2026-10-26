<?php

namespace App\Concerns;

use App\Actions\Forwarding\ForwardApplication;
use App\Enums\ForwardEmailStatus;
use App\Enums\ForwardTarget;
use App\Models\ApplicationForward;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * The forward form and the forward/remark chain, shared by the Admin
 * Officer's and the HOD/Dean's application views. The using component
 * must have a public Application $application property.
 */
trait ForwardsApplications
{
    public string $forward_target = '';

    public string $forward_note = '';

    /**
     * Each target this user may forward to, with the reason it is
     * unavailable right now (no active HOD/Dean, no Registrar email) or
     * null when it is available.
     *
     * @return array<string, string|null>
     */
    #[Computed]
    public function forwardOptions(): array
    {
        $user = Auth::user();
        $role = $user->staffRole();

        if ($role === null) {
            return [];
        }

        $forwardApplication = app(ForwardApplication::class);
        $options = [];

        foreach (ForwardTarget::allowedFrom($role) as $target) {
            if ($user->can('forward', [$this->application, $target])) {
                $options[$target->value] = $forwardApplication->unavailableReason($this->application, $target);
            }
        }

        return $options;
    }

    /**
     * @return Collection<int, ApplicationForward>
     */
    #[Computed]
    public function forwards(): Collection
    {
        $this->authorize('viewForwardChain', $this->application);

        return $this->application->forwards()
            ->with(['sender', 'recipient', 'remarks.author'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Forwards are emailed from the forwarder's own Gmail, so without a
     * usable connection the form shows this instead. Null when ready.
     */
    #[Computed]
    public function gmailRequiredMessage(): ?string
    {
        $user = Auth::user();

        return $user->hasConnectedGmail() ? null : ForwardApplication::gmailRequiredMessage($user);
    }

    /**
     * Send (or retry) the email of one of this user's own forwards on
     * this application that is pending or failed.
     */
    public function sendForwardEmail(int $forwardId, ForwardApplication $forwardApplication): void
    {
        $forward = $this->application->forwards()->findOrFail($forwardId);

        $this->authorize('sendEmail', $forward);

        try {
            $forwardApplication->resendEmail($forward);
        } catch (ValidationException $exception) {
            Flux::toast(variant: 'danger', text: $exception->validator->errors()->first());

            return;
        }

        unset($this->forwards);

        $forward->refresh();

        if ($forward->email_status === ForwardEmailStatus::Sent) {
            Flux::toast(variant: 'success', text: __('Forward email sent.'));
        } elseif ($forward->email_status === ForwardEmailStatus::Failed) {
            Flux::toast(variant: 'danger', text: (string) $forward->email_error);
        } else {
            Flux::toast(text: __('Forward email queued for sending.'));
        }
    }

    public function forward(ForwardApplication $forwardApplication): void
    {
        $validated = $this->validate([
            'forward_target' => ['required', Rule::enum(ForwardTarget::class)],
            'forward_note' => ['required', 'string', 'max:1000'],
        ], [
            'forward_target.required' => __('Choose where to forward the application.'),
            'forward_note.required' => __('Add a note for the person you are forwarding to.'),
        ]);

        $target = ForwardTarget::from($validated['forward_target']);

        $this->authorize('forward', [$this->application, $target]);

        $forwardApplication->handle($this->application, Auth::user(), $target, $validated['forward_note']);

        $this->reset('forward_target', 'forward_note');
        $this->application->refresh();
        unset($this->forwards, $this->forwardOptions);
        $this->afterForwarding();

        $this->modal('confirm-forward')->close();
        Flux::toast(variant: 'success', text: __('Application forwarded to the :target.', ['target' => $target->label()]));
    }

    /**
     * Refresh anything else on the page that a forward changes.
     */
    protected function afterForwarding(): void {}
}
