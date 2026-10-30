<?php

namespace App\Concerns;

use App\Actions\Forwarding\ForwardApplication;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\ForwardTarget;
use App\Models\Office;
use App\Models\OfficeContact;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;

/**
 * The forward form, shared by the Admin Officer's and the HOD/Dean's
 * application views. Forwards land in the correspondence thread (the
 * correspondence.thread component on the same page). The using component
 * must have a public Application $application property.
 */
trait ForwardsApplications
{
    public string $forward_target = '';

    public string $forward_note = '';

    public string $forward_office_id = '';

    public string $forward_contact_id = '';

    /**
     * Idempotency key of the forward being written, so a double click
     * forwards (and emails) only once.
     */
    #[Locked]
    public string $forward_key = '';

    public function mountForwardsApplications(): void
    {
        $this->forward_key = (string) Str::uuid();
    }

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
                $options[$target->value] = $forwardApplication->unavailableReason($this->application, $target, $user);
            }
        }

        return $options;
    }

    /**
     * Active offices with an active contact that this user may forward to.
     *
     * @return Collection<int, Office>
     */
    #[Computed]
    public function forwardOffices(): Collection
    {
        return Office::query()
            ->selectableBy(Auth::user(), $this->application->department_id)
            ->whereHas('contacts', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get();
    }

    /**
     * Active contacts of the chosen office (empty until one is chosen).
     *
     * @return Collection<int, OfficeContact>
     */
    #[Computed]
    public function forwardContacts(): Collection
    {
        $office = $this->forwardOffices()->firstWhere('id', (int) $this->forward_office_id);

        return $office === null
            ? new Collection
            : $office->contacts()->where('is_active', true)->orderBy('name')->get();
    }

    public function updatedForwardOfficeId(): void
    {
        $this->forward_contact_id = '';
        unset($this->forwardContacts);
    }

    public function forward(ForwardApplication $forwardApplication): void
    {
        $validated = $this->validate([
            'forward_target' => ['required', Rule::enum(ForwardTarget::class)],
            'forward_note' => ['required', 'string', 'max:1000'],
            'forward_office_id' => [Rule::requiredIf($this->forward_target === ForwardTarget::Office->value)],
            'forward_contact_id' => [Rule::requiredIf($this->forward_target === ForwardTarget::Office->value)],
        ], [
            'forward_target.required' => __('Choose where to forward the application.'),
            'forward_office_id.required' => __('Choose an office.'),
            'forward_contact_id.required' => __('Choose a contact.'),
            'forward_note.required' => __('Add a note for the person you are forwarding to.'),
        ]);

        $target = ForwardTarget::from($validated['forward_target']);

        $this->authorize('forward', [$this->application, $target]);

        $contact = $target === ForwardTarget::Office
            ? OfficeContact::query()->where('office_id', (int) $this->forward_office_id)->find((int) $this->forward_contact_id)
            : null;

        $forward = $forwardApplication->handle($this->application, Auth::user(), $target, $validated['forward_note'], $this->forward_key, $contact);

        $this->reset('forward_target', 'forward_note', 'forward_office_id', 'forward_contact_id');
        unset($this->forwardContacts);
        $this->forward_key = (string) Str::uuid();
        $this->application->refresh();
        unset($this->forwardOptions);
        $this->afterForwarding();
        $this->dispatch('correspondence-posted');

        $this->modal('confirm-forward')->close();
        $destination = $target === ForwardTarget::Office ? $forward->recipientName() : __('the :target', ['target' => $target->label()]);

        Flux::toast(variant: 'success', text: $forward->email_status === CorrespondenceEmailStatus::AwaitingGmail
            ? __('Application forwarded to :destination. It will be emailed once you connect your Gmail.', ['destination' => $destination])
            : __('Application forwarded to :destination.', ['destination' => $destination]));
    }

    /**
     * Refresh anything else on the page that a forward changes.
     */
    protected function afterForwarding(): void {}
}
