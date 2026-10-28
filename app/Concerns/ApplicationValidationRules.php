<?php

namespace App\Concerns;

use App\Models\ApplicationCategory;
use App\Models\Setting;
use Illuminate\Contracts\Support\MessageBag;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait ApplicationValidationRules
{
    /**
     * Largest allowed attachment, in kilobytes (5 MB).
     */
    public const ATTACHMENT_MAX_KILOBYTES = 5120;

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function categoryIdRules(int $departmentId): array
    {
        return [
            'required',
            Rule::exists(ApplicationCategory::class, 'id')
                ->where('department_id', $departmentId)
                ->where('is_active', true),
        ];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function subjectRules(): array
    {
        return ['required', 'string', 'max:'.$this->subjectMaxLength()];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function bodyRules(): array
    {
        return ['required', 'string', 'max:'.$this->bodyMaxLength()];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function attachmentsRules(): array
    {
        return ['nullable', 'array', 'max:3'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function attachmentRules(): array
    {
        return ['file', 'mimes:pdf,jpg,jpeg,png,doc,docx', 'max:'.self::ATTACHMENT_MAX_KILOBYTES];
    }

    protected function subjectMaxLength(): int
    {
        return (int) Setting::get('applications.subject_max_length', '100');
    }

    protected function bodyMaxLength(): int
    {
        return (int) Setting::get('applications.body_max_length', '500');
    }

    /**
     * Builds an exact, per-file mimes/max message naming the file and (for
     * size) its actual size in MB, keyed by "attachments.N.rule" so it
     * overrides any generic message for the same field.
     *
     * @param  array<int, TemporaryUploadedFile>  $files
     * @return array<string, string>
     */
    protected function attachmentMessages(array $files): array
    {
        $messages = [];

        foreach ($files as $index => $file) {
            $name = $file->getClientOriginalName();

            $messages["attachments.{$index}.mimes"] = "{$name}: only PDF, JPG, PNG, DOC or DOCX files are allowed.";
            $messages["attachments.{$index}.max"] = sprintf(
                '%s is %s MB. Maximum size is 5 MB.',
                $name,
                number_format($file->getSize() / 1048576, 1),
            );
        }

        return $messages;
    }

    /**
     * Index (int) of each "attachments.N" key in a validator error bag —
     * i.e. the files that failed their own mimes/max rule, as opposed to
     * the "attachments" (count) key, which names no single file.
     *
     * @return array<int, int>
     */
    protected function invalidAttachmentIndexes(MessageBag $errors): array
    {
        $indexes = [];

        foreach ($errors->keys() as $key) {
            if (preg_match('/^attachments\.(\d+)$/', (string) $key, $matches)) {
                $indexes[] = (int) $matches[1];
            }
        }

        return $indexes;
    }

    /**
     * @return array<string, string>
     */
    protected function applicationMessages(): array
    {
        return [
            'category_id.required' => __('Select a category.'),
            'category_id.exists' => __('Select a valid category.'),
            'subject.required' => __('Enter a subject.'),
            'body.required' => __('Describe your issue.'),
            'attachments.max' => __('You can attach up to 3 files.'),
        ];
    }
}
