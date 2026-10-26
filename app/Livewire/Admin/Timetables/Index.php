<?php

namespace App\Livewire\Admin\Timetables;

use App\Actions\Timetable\ImportTimetable;
use App\Actions\Timetable\PublishTimetableVersion;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Scopes\AdminDepartmentScope;
use App\Models\TimetableVersion;
use Flux\Flux;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

#[Title('Timetable')]
class Index extends Component
{
    use WithFileUploads, WithPagination;

    public mixed $file = null;

    /**
     * Super Admin only: the department to upload for, and to list.
     */
    public string $department_id = '';

    public function mount(): void
    {
        $this->authorize('viewAny', TimetableVersion::class);
    }

    #[Computed]
    public function isSuperAdmin(): bool
    {
        return Auth::user()->hasRole(RoleName::SuperAdmin->value);
    }

    /**
     * @return Collection<int, Department>
     */
    #[Computed]
    public function departments(): Collection
    {
        return Department::query()->orderBy('name')->get();
    }

    /**
     * @return LengthAwarePaginator<int, TimetableVersion>
     */
    #[Computed]
    public function versions(): LengthAwarePaginator
    {
        return TimetableVersion::query()
            ->with(['department', 'uploader'])
            ->when($this->isSuperAdmin() && $this->department_id !== '', fn ($query) => $query->where('department_id', $this->department_id))
            ->latest('id')
            ->paginate(15);
    }

    public function updatedDepartmentId(): void
    {
        $this->resetPage();
    }

    /**
     * Not named "upload": in the browser, $wire.upload is Livewire's own
     * file-upload helper, so wire:submit="upload" never reaches PHP.
     */
    public function uploadTimetable(ImportTimetable $importTimetable): void
    {
        $this->authorize('create', TimetableVersion::class);

        $validated = $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx', 'extensions:xlsx', 'max:10240'],
            'department_id' => $this->isSuperAdmin()
                ? ['required', Rule::exists(Department::class, 'id')]
                : ['nullable'],
        ], [
            'file.mimes' => __('Upload the timetable as an Excel .xlsx file.'),
            'file.extensions' => __('Upload the timetable as an Excel .xlsx file.'),
            'department_id.required' => __('Choose the department this timetable belongs to.'),
        ]);

        /** @var UploadedFile $file */
        $file = $validated['file'];
        $departmentId = $this->isSuperAdmin() ? (int) $validated['department_id'] : (int) Auth::user()->department_id;

        try {
            $version = $importTimetable->handle($file, $departmentId, Auth::user());
        } catch (SpreadsheetException) {
            throw ValidationException::withMessages([
                'file' => __('This file could not be read as an Excel workbook.'),
            ]);
        }

        $this->reset('file');

        Flux::toast(variant: 'success', text: __('Timetable uploaded. Check the preview, then publish it.'));

        $this->redirectRoute($this->routePrefix().'timetable.show', ['version' => $version->id], navigate: true);
    }

    /**
     * Restore an archived version (or publish a draft) straight from the list.
     */
    public function publish(int $id, PublishTimetableVersion $publishTimetableVersion): void
    {
        $version = TimetableVersion::withoutGlobalScope(AdminDepartmentScope::class)->findOrFail($id);
        $this->authorize('publish', $version);

        $publishTimetableVersion->handle($version, Auth::user());

        unset($this->versions);
        $this->modal('publish-version-'.$id)->close();
        Flux::toast(variant: 'success', text: __('Version :number is now the published timetable.', ['number' => $version->version_no]));
    }

    public function routePrefix(): string
    {
        return $this->isSuperAdmin() ? 'super-admin.' : 'admin.';
    }

    public function render(): View
    {
        return view('livewire.admin.timetables.index');
    }
}
