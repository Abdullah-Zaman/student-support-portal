<?php

namespace App\Livewire\Admin\Offices;

use App\Models\Office;
use App\Models\OfficeContact;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * The Admin Officer's offices and their contacts: the recipients of
 * forwards. Everything is scoped to the Admin Officer's own department
 * and authorized through the OfficePolicy on every action.
 */
#[Title('Offices')]
class Index extends Component
{
    #[Locked]
    public ?int $editing_office_id = null;

    public string $office_name = '';

    public string $office_code = '';

    public bool $available_to_hod = false;

    public bool $available_to_dean = false;

    #[Locked]
    public ?int $contact_office_id = null;

    #[Locked]
    public ?int $editing_contact_id = null;

    public string $contact_name = '';

    public string $contact_designation = '';

    public string $contact_email = '';

    #[Locked]
    public string $toggle_type = '';

    #[Locked]
    public ?int $toggle_id = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Office::class);
    }

    /**
     * @return Collection<int, Office>
     */
    #[Computed]
    public function offices(): Collection
    {
        return Office::query()
            ->where('department_id', Auth::user()->department_id)
            ->with('contacts')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function startCreateOffice(): void
    {
        $this->authorize('create', Office::class);

        $this->reset(['editing_office_id', 'office_name', 'office_code', 'available_to_hod', 'available_to_dean']);
        $this->resetValidation();
        $this->modal('office-form')->show();
    }

    public function startEditOffice(int $id): void
    {
        $office = $this->findOffice($id);
        $this->authorize('update', $office);

        $this->editing_office_id = $office->id;
        $this->office_name = $office->name;
        $this->office_code = (string) $office->code;
        $this->available_to_hod = $office->available_to_hod;
        $this->available_to_dean = $office->available_to_dean;
        $this->resetValidation();
        $this->modal('office-form')->show();
    }

    public function saveOffice(): void
    {
        $office = $this->editing_office_id ? $this->findOffice($this->editing_office_id) : null;

        $this->authorize($office ? 'update' : 'create', $office ?? Office::class);

        $departmentId = $office === null ? Auth::user()->department_id : $office->department_id;

        $validated = $this->validate([
            'office_name' => [
                'required', 'string', 'max:100',
                Rule::unique('offices', 'name')->where('department_id', $departmentId)->ignore($office?->id),
            ],
            'office_code' => ['nullable', 'string', 'max:20'],
            'available_to_hod' => ['boolean'],
            'available_to_dean' => ['boolean'],
        ], [
            'office_name.unique' => __('Your department already has an office with that name.'),
        ]);

        $attributes = [
            'name' => $validated['office_name'],
            'code' => ($validated['office_code'] ?? '') !== '' ? $validated['office_code'] : null,
            'available_to_hod' => $validated['available_to_hod'],
            'available_to_dean' => $validated['available_to_dean'],
        ];

        if ($office === null) {
            Office::create([...$attributes, 'department_id' => $departmentId, 'is_active' => true]);
        } else {
            $office->update($attributes);
        }

        unset($this->offices);
        $this->modal('office-form')->close();
        Flux::toast(variant: 'success', text: $office ? __('Office updated.') : __('Office created.'));
    }

    public function startCreateContact(int $officeId): void
    {
        $office = $this->findOffice($officeId);
        $this->authorize('update', $office);

        $this->reset(['editing_contact_id', 'contact_name', 'contact_designation', 'contact_email']);
        $this->contact_office_id = $office->id;
        $this->resetValidation();
        $this->modal('contact-form')->show();
    }

    public function startEditContact(int $id): void
    {
        $contact = $this->findContact($id);
        $this->authorize('update', $contact->office);

        $this->editing_contact_id = $contact->id;
        $this->contact_office_id = $contact->office_id;
        $this->contact_name = $contact->name;
        $this->contact_designation = (string) $contact->designation;
        $this->contact_email = $contact->email;
        $this->resetValidation();
        $this->modal('contact-form')->show();
    }

    public function saveContact(): void
    {
        $contact = $this->editing_contact_id ? $this->findContact($this->editing_contact_id) : null;
        $office = $contact ? $contact->office : $this->findOffice((int) $this->contact_office_id);

        $this->authorize('update', $office);

        $this->contact_email = mb_strtolower(trim($this->contact_email));

        $validated = $this->validate([
            'contact_name' => ['required', 'string', 'max:100'],
            'contact_designation' => ['nullable', 'string', 'max:100'],
            'contact_email' => [
                'required', 'string', 'max:255', 'email:rfc',
                Rule::unique('office_contacts', 'email')->where('office_id', $office->id)->ignore($contact?->id),
            ],
        ], [
            'contact_email.unique' => __('This office already has a contact with that email.'),
        ]);

        $attributes = [
            'name' => $validated['contact_name'],
            'designation' => ($validated['contact_designation'] ?? '') !== '' ? $validated['contact_designation'] : null,
            'email' => $validated['contact_email'],
        ];

        if ($contact === null) {
            $office->contacts()->create([...$attributes, 'is_active' => true]);
        } else {
            $contact->update($attributes);
        }

        unset($this->offices);
        $this->modal('contact-form')->close();
        Flux::toast(variant: 'success', text: $contact ? __('Contact updated.') : __('Contact added.'));
    }

    /**
     * Ask before deactivating (or reactivating) an office or contact.
     */
    public function confirmToggle(string $type, int $id): void
    {
        $this->authorize('update', $this->officeOf($type, $id));

        $this->toggle_type = $type === 'contact' ? 'contact' : 'office';
        $this->toggle_id = $id;
        $this->modal('confirm-toggle')->show();
    }

    public function applyToggle(): void
    {
        $record = $this->toggle_type === 'contact'
            ? $this->findContact((int) $this->toggle_id)
            : $this->findOffice((int) $this->toggle_id);

        $this->authorize('update', $this->officeOf($this->toggle_type, (int) $this->toggle_id));

        $record->update(['is_active' => ! $record->is_active]);

        unset($this->offices);
        $this->reset(['toggle_type', 'toggle_id']);
        $this->modal('confirm-toggle')->close();
        Flux::toast(variant: 'success', text: $record->is_active ? __('Activated.') : __('Deactivated.'));
    }

    private function officeOf(string $type, int $id): Office
    {
        return $type === 'contact' ? $this->findContact($id)->office : $this->findOffice($id);
    }

    private function findOffice(int $id): Office
    {
        return Office::query()->findOrFail($id);
    }

    private function findContact(int $id): OfficeContact
    {
        return OfficeContact::query()->findOrFail($id);
    }

    public function render(): View
    {
        return view('livewire.admin.offices.index');
    }
}
