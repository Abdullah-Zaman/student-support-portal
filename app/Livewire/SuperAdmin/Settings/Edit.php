<?php

namespace App\Livewire\SuperAdmin\Settings;

use App\Actions\Forwarding\ForwardApplication;
use App\Models\Setting;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('System Settings')]
class Edit extends Component
{
    public int $subject_max_length;

    public int $body_max_length;

    /**
     * Where forwards to the Registrar go (emailed in Phase D).
     */
    public string $registrar_email = '';

    public function mount(): void
    {
        $this->authorize('manage', Setting::class);

        $this->subject_max_length = (int) Setting::get('applications.subject_max_length', '100');
        $this->body_max_length = (int) Setting::get('applications.body_max_length', '500');
        $this->registrar_email = (string) ForwardApplication::registrarEmail();
    }

    public function save(): void
    {
        $this->authorize('manage', Setting::class);

        $validated = $this->validate([
            'subject_max_length' => ['required', 'integer', 'between:50,200'],
            'body_max_length' => ['required', 'integer', 'between:200,2000'],
            'registrar_email' => ['nullable', 'string', 'email', 'max:255'],
        ]);

        Setting::set('applications.subject_max_length', (string) $validated['subject_max_length']);
        Setting::set('applications.body_max_length', (string) $validated['body_max_length']);
        Setting::set(ForwardApplication::REGISTRAR_EMAIL_SETTING, $validated['registrar_email'] ? strtolower($validated['registrar_email']) : null);

        Flux::toast(variant: 'success', text: __('Settings saved.'));
    }

    public function render(): View
    {
        return view('livewire.super-admin.settings.edit');
    }
}
