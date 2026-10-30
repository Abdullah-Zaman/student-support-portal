<?php

namespace App\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;

trait AnnouncementValidationRules
{
    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function titleRules(): array
    {
        return ['required', 'string', 'max:150'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function bodyRules(): array
    {
        return ['required', 'string', 'max:2000'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function imageRules(): array
    {
        return ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:2048'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function attachmentRules(): array
    {
        return ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function publishAtRules(): array
    {
        return ['nullable', 'date'];
    }

    /**
     * Must be after whichever publish date applies — the one being
     * submitted if given, otherwise now (an unscheduled post is "published"
     * the moment it's saved).
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function expiresAtRules(?string $publishAt): array
    {
        return ['nullable', 'date', 'after:'.($publishAt ?: now()->toDateTimeString())];
    }

    /**
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function surveyUrlRules(): array
    {
        return ['nullable', 'url:https', 'max:500'];
    }

    /**
     * @return array<string, string>
     */
    protected function announcementMessages(): array
    {
        return [
            'title.required' => __('Enter a title.'),
            'title.max' => __('The title must be 150 characters or fewer.'),
            'body.required' => __('Enter the announcement body.'),
            'body.max' => __('The body must be 2000 characters or fewer.'),
            'image.mimes' => __('The image must be a JPG, JPEG, PNG or WEBP file.'),
            'image.max' => __('The image must be 2 MB or smaller.'),
            'attachment.mimes' => __('The attachment must be a PDF, DOC or DOCX file.'),
            'attachment.max' => __('The attachment must be 5 MB or smaller.'),
            'survey_url.url' => __('The form link must be a valid https:// address.'),
            'expires_at.after' => __('The expiry date must be after the publish date.'),
        ];
    }
}
