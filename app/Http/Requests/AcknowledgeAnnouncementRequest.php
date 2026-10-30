<?php

namespace App\Http\Requests;

use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('acknowledge', $this->route('announcement'));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        /** @var Announcement $announcement */
        $announcement = $this->route('announcement');

        return [
            'survey_marked_done' => $announcement->survey_url ? ['accepted'] : ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'survey_marked_done.accepted' => __('Please confirm that you have filled the form.'),
        ];
    }
}
