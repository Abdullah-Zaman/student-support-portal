<?php

namespace App\Models;

use App\Enums\ApplicationEventType;
use App\Enums\ForwardTarget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property ApplicationEventType $event_type
 */
class ApplicationEvent extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'user_id',
        'event_type',
        'from_value',
        'to_value',
        'meta',
        'visible_to_student',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => ApplicationEventType::class,
            'meta' => 'array',
            'visible_to_student' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A short, human-readable description for activity feeds (e.g. the
     * student dashboard's "Latest updates" panel).
     */
    public function description(): string
    {
        $applicationNo = $this->application->application_no;

        return match ($this->event_type) {
            ApplicationEventType::Submitted => __(':number was submitted.', ['number' => $applicationNo]),
            ApplicationEventType::StatusChanged => __(':number moved to :status.', ['number' => $applicationNo, 'status' => Str::headline((string) $this->to_value)]),
            ApplicationEventType::PriorityChanged => __('The priority on :number was updated.', ['number' => $applicationNo]),
            ApplicationEventType::MessageSent => __('The Admin Office sent a message on :number.', ['number' => $applicationNo]),
            ApplicationEventType::InfoRequested => __('The Admin Office requested more information on :number.', ['number' => $applicationNo]),
            ApplicationEventType::DocumentRequested => __('The Admin Office requested a document on :number.', ['number' => $applicationNo]),
            ApplicationEventType::StudentResponded => __('You responded on :number.', ['number' => $applicationNo]),
            ApplicationEventType::AttachmentUploaded => __('A file was uploaded on :number.', ['number' => $applicationNo]),
            ApplicationEventType::Resolved => __(':number was marked resolved.', ['number' => $applicationNo]),
            ApplicationEventType::Closed => __(':number was closed.', ['number' => $applicationNo]),
            ApplicationEventType::Rejected => __(':number was rejected.', ['number' => $applicationNo]),
            ApplicationEventType::Forwarded => __(':number was forwarded to the :target for review.', ['number' => $applicationNo, 'target' => $this->forwardTargetLabel()]),
        };
    }

    /**
     * A human-readable description for a single application's own timeline,
     * where naming the application again on every row is just noise.
     */
    public function timelineDescription(): string
    {
        return match ($this->event_type) {
            ApplicationEventType::Submitted => __('Application submitted.'),
            ApplicationEventType::StatusChanged => __('Status changed from :from to :to.', [
                'from' => self::humanizeValue((string) $this->from_value),
                'to' => self::humanizeValue((string) $this->to_value),
            ]),
            ApplicationEventType::PriorityChanged => __('Priority changed from :from to :to.', [
                'from' => self::humanizeValue((string) $this->from_value),
                'to' => self::humanizeValue((string) $this->to_value),
            ]),
            ApplicationEventType::MessageSent => __('The Admin Office sent a message.'),
            ApplicationEventType::InfoRequested => __('The Admin Office requested more information.'),
            ApplicationEventType::DocumentRequested => __('The Admin Office requested a document.'),
            ApplicationEventType::StudentResponded => __('The student responded.'),
            ApplicationEventType::AttachmentUploaded => __('A file was uploaded.'),
            ApplicationEventType::Resolved => __('Application resolved.'),
            ApplicationEventType::Closed => __('Application closed.'),
            ApplicationEventType::Rejected => __('Application rejected.'),
            ApplicationEventType::Forwarded => __('Forwarded to the :target.', ['target' => $this->forwardTargetLabel()]),
        };
    }

    /**
     * "HOD", "Dean" or "Registrar" for a Forwarded event. Only the
     * destination is ever shown; the note and remarks stay staff-only.
     */
    private function forwardTargetLabel(): string
    {
        return ForwardTarget::tryFrom((string) $this->to_value)?->label() ?? self::humanizeValue((string) $this->to_value);
    }

    /**
     * "under_review" -> "Under review", "urgent" -> "Urgent".
     */
    private static function humanizeValue(string $value): string
    {
        return Str::of($value)->replace('_', ' ')->lower()->ucfirst()->toString();
    }
}
