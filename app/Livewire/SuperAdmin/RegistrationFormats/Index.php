<?php

namespace App\Livewire\SuperAdmin\RegistrationFormats;

use App\Models\Program;
use App\Models\RegistrationFormat;
use App\Support\Registration\FormatTemplate;
use App\Support\Registration\RegistrationFormats;
use App\Support\Registration\StudentEmailDomain;
use App\Support\RegistrationNumber;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * "Additional student registration formats": old or pass-out students
 * can register with a different email and registration number, as long
 * as the email is still at superior.edu.pk (locked in code). Patterns
 * are simple templates, never raw regex. Super Admin only; every change
 * is logged.
 *
 * @property-read Collection<int, RegistrationFormat> $formats
 * @property-read Collection<int, Program> $programs
 * @property-read array<string, mixed>|null $testResult
 */
#[Title('Registration formats')]
class Index extends Component
{
    public ?int $editing_id = null;

    public string $name = '';

    public string $email_template = '';

    public string $registration_no_template = '';

    public string $program_code = '';

    public string $batch = '';

    public bool $is_alumni = false;

    public bool $is_active = true;

    public string $test_email = '';

    public string $test_registration_no = '';

    public string $test_program = '';

    public function mount(): void
    {
        $this->authorize('viewAny', RegistrationFormat::class);
    }

    /**
     * @return Collection<int, RegistrationFormat>
     */
    #[Computed]
    public function formats(): Collection
    {
        return RegistrationFormat::query()->orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, Program>
     */
    #[Computed]
    public function programs(): Collection
    {
        return Program::withoutGlobalScopes()->orderBy('code')->get();
    }

    /**
     * What the live test box says about the typed email and registration
     * number: the locked domain, the current format and the first active
     * additional format that accepts them.
     *
     * @return array{domainOk: bool, current: bool, rule: RegistrationFormat|null, program: string|null, batch: string|null, alumni: bool, inactiveRule: RegistrationFormat|null}|null
     */
    #[Computed]
    public function testResult(): ?array
    {
        $email = trim($this->test_email);
        $registrationNo = strtoupper(trim($this->test_registration_no));

        if ($email === '' && $registrationNo === '') {
            return null;
        }

        $program = $this->test_program !== '' ? $this->test_program : null;
        $domainOk = StudentEmailDomain::isValid($email);
        $current = $domainOk
            && RegistrationNumber::parse($registrationNo) !== null
            && StudentEmailDomain::normalize($email) === strtolower($registrationNo).StudentEmailDomain::DOMAIN;
        $match = $domainOk && ! $current ? RegistrationFormats::matchPair($registrationNo, $email, $program) : null;
        $inactive = $domainOk && ! $current && $match === null
            ? RegistrationFormats::matchPair($registrationNo, $email, $program, activeOnly: false)
            : null;

        return [
            'domainOk' => $domainOk,
            'current' => $current,
            'rule' => $match['rule'] ?? null,
            'program' => $match['program_code'] ?? null,
            'batch' => $match['batch'] ?? null,
            'alumni' => (bool) ($match['is_alumni'] ?? false),
            'inactiveRule' => $inactive['rule'] ?? null,
        ];
    }

    public function startCreate(): void
    {
        $this->authorize('create', RegistrationFormat::class);

        $this->resetForm();
        $this->modal('format-form')->show();
    }

    public function startEdit(int $id): void
    {
        $format = RegistrationFormat::query()->findOrFail($id);
        $this->authorize('update', $format);

        $this->editing_id = $format->id;
        $this->name = $format->name;
        $this->email_template = $format->email_template;
        $this->registration_no_template = $format->registration_no_template;
        $this->program_code = (string) $format->program_code;
        $this->batch = (string) $format->batch;
        $this->is_alumni = $format->is_alumni;
        $this->is_active = $format->is_active;
        $this->resetValidation();

        $this->modal('format-form')->show();
    }

    public function save(): void
    {
        $format = $this->editing_id ? RegistrationFormat::query()->findOrFail($this->editing_id) : null;

        $this->authorize($format ? 'update' : 'create', $format ?? RegistrationFormat::class);

        $this->email_template = $this->emailLocalTemplate($this->email_template);
        $this->registration_no_template = trim($this->registration_no_template);
        $this->batch = strtoupper(trim($this->batch));

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'email_template' => ['required', 'string', 'max:'.FormatTemplate::MAX_TEMPLATE_LENGTH, fn ($attribute, $value, $fail) => $this->failIfBadTemplate($value, $fail)],
            'registration_no_template' => ['required', 'string', 'max:'.FormatTemplate::MAX_TEMPLATE_LENGTH, fn ($attribute, $value, $fail) => $this->failIfBadTemplate($value, $fail)],
            'program_code' => ['nullable', Rule::exists(Program::class, 'code')],
            'batch' => ['nullable', 'regex:/^[FS]\d{2}$/'],
            'is_alumni' => ['boolean'],
            'is_active' => ['boolean'],
        ], [
            'batch.regex' => __('Use a batch like F21 or S23.'),
        ]);

        if ($validated['batch'] === '' && ! FormatTemplate::capturesBatch($validated['registration_no_template'])) {
            throw ValidationException::withMessages([
                'batch' => __('Set a batch, or put {BATCH} in the registration number pattern, so students get a batch.'),
            ]);
        }

        $attributes = [
            'name' => trim($validated['name']),
            'email_template' => $validated['email_template'],
            'registration_no_template' => $validated['registration_no_template'],
            'program_code' => $validated['program_code'] !== '' ? $validated['program_code'] : null,
            'batch' => $validated['batch'] !== '' ? $validated['batch'] : null,
            'is_alumni' => (bool) $validated['is_alumni'],
            'is_active' => (bool) $validated['is_active'],
        ];

        if ($format === null) {
            $format = RegistrationFormat::create($attributes);
            $this->log('registration format created', $format);
        } else {
            $wasActive = $format->is_active;
            $format->update($attributes);
            $this->log('registration format updated', $format);

            if ($wasActive !== $format->is_active) {
                $this->log($format->is_active ? 'registration format enabled' : 'registration format disabled', $format);
            }
        }

        unset($this->formats);
        $this->modal('format-form')->close();
        Flux::toast(variant: 'success', text: $this->editing_id ? __('Format updated.') : __('Format added.'));
    }

    public function toggleActive(int $id): void
    {
        $format = RegistrationFormat::query()->findOrFail($id);
        $this->authorize('update', $format);

        $format->update(['is_active' => ! $format->is_active]);
        $this->log($format->is_active ? 'registration format enabled' : 'registration format disabled', $format);

        unset($this->formats);
        Flux::toast(variant: 'success', text: $format->is_active ? __('Format enabled.') : __('Format disabled.'));
    }

    public function render(): View
    {
        return view('livewire.super-admin.registration-formats.index');
    }

    private function resetForm(): void
    {
        $this->reset(['editing_id', 'name', 'email_template', 'registration_no_template', 'program_code', 'batch']);
        $this->is_alumni = false;
        $this->is_active = true;
        $this->resetValidation();
    }

    /**
     * The part before the domain. A pasted full address must end in the
     * locked domain; any other domain is rejected, not trimmed away.
     *
     * @throws ValidationException
     */
    private function emailLocalTemplate(string $input): string
    {
        $input = trim($input);

        if (! str_contains($input, '@')) {
            return $input;
        }

        if (substr_count($input, '@') !== 1 || ! str_ends_with(strtolower($input), StudentEmailDomain::DOMAIN)) {
            throw ValidationException::withMessages([
                'email_template' => __('Student emails must end in :domain. No other domain is allowed.', ['domain' => StudentEmailDomain::DOMAIN]),
            ]);
        }

        return substr($input, 0, -strlen(StudentEmailDomain::DOMAIN));
    }

    private function failIfBadTemplate(mixed $value, callable $fail): void
    {
        $problem = FormatTemplate::problem((string) $value);

        if ($problem !== null) {
            $fail($problem);
        }
    }

    private function log(string $description, RegistrationFormat $format): void
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($format)
            ->withProperties([
                'name' => $format->name,
                'email_template' => $format->email_template,
                'registration_no_template' => $format->registration_no_template,
                'program_code' => $format->program_code,
                'batch' => $format->batch,
                'is_alumni' => $format->is_alumni,
                'is_active' => $format->is_active,
            ])
            ->log($description);
    }
}
