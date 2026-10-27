@php
    $label = 'padding:6px 12px 6px 0;color:#6B626B;font-size:13px;white-space:nowrap;vertical-align:top;';
    $value = 'padding:6px 0;color:#1F1A1E;font-size:14px;vertical-align:top;';
    $sectionHeading = 'margin:24px 0 8px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#875A7B;';
    $box = 'margin:0;padding:12px 14px;background-color:#F6F4F5;border-radius:10px;font-size:14px;line-height:1.6;color:#3B3339;white-space:pre-line;';
    $intro = match ($message->type) {
        \App\Enums\CorrespondenceType::Forward => __('Forwarded by :name (:role)', ['name' => $message->senderName(), 'role' => $message->from_party->label()]),
        \App\Enums\CorrespondenceType::Remark => __('Remark by :name (:role)', ['name' => $message->senderName(), 'role' => $message->from_party->label()]),
        default => __('Reply from :name (:role)', ['name' => $message->senderName(), 'role' => $message->from_party->label()]),
    };
@endphp
<x-mail.gmail-layout :logo="$logo" :title="$application->application_no">
<p style="margin:0 0 6px;font-size:13px;color:#6B626B;">{{ $intro }}</p>
<h1 style="margin:0;font-size:20px;line-height:1.3;color:#1F1A1E;">{{ $application->application_no }}</h1>
<p style="margin:4px 0 0;font-size:15px;color:#3B3339;">{{ $application->subject }}</p>

@if ($message->remark_type)
<p style="margin:20px 0 0;font-size:14px;font-weight:700;color:#1F1A1E;">{{ $message->remark_type->label() }}</p>
@endif

@if ($message->body)
<h2 style="{{ $sectionHeading }}">{{ __('Message from :role', ['role' => $message->from_party->label()]) }}</h2>
<p style="{{ $box }}">{{ $message->body }}</p>
@endif

@if ($isForward)
<h2 style="{{ $sectionHeading }}">{{ __('Student') }}</h2>
<table cellpadding="0" cellspacing="0" role="presentation">
<tr><td style="{{ $label }}">{{ __('Name') }}</td><td style="{{ $value }}">{{ $student->user->name }}</td></tr>
<tr><td style="{{ $label }}">{{ __('Registration No.') }}</td><td style="{{ $value }}">{{ $student->registration_no }}</td></tr>
<tr><td style="{{ $label }}">{{ __('Program') }}</td><td style="{{ $value }}">{{ $student->program?->name }}{{ $student->program ? ' ('.$student->program->code.')' : '' }}</td></tr>
<tr><td style="{{ $label }}">{{ __('Batch') }}</td><td style="{{ $value }}">{{ $student->batch_label }}</td></tr>
<tr><td style="{{ $label }}">{{ __('Semester') }}</td><td style="{{ $value }}">{{ $application->semester_at_submission }}</td></tr>
<tr><td style="{{ $label }}">{{ __('Email') }}</td><td style="{{ $value }}">{{ $student->user->email }}</td></tr>
</table>

<h2 style="{{ $sectionHeading }}">{{ __('Application') }}</h2>
<table cellpadding="0" cellspacing="0" role="presentation">
<tr><td style="{{ $label }}">{{ __('Category') }}</td><td style="{{ $value }}">{{ $application->category?->name }}</td></tr>
<tr><td style="{{ $label }}">{{ __('Submitted') }}</td><td style="{{ $value }}">{{ $application->created_at->format('j M Y, g:i A') }}</td></tr>
<tr><td style="{{ $label }}">{{ __('Status') }}</td><td style="{{ $value }}">{{ \Illuminate\Support\Str::headline($application->status->value) }}</td></tr>
</table>
<p style="{{ $box }};margin-top:10px;">{{ $application->body }}</p>

@if ($earlier->isNotEmpty())
<h2 style="{{ $sectionHeading }}">{{ __('Earlier correspondence') }}</h2>
@foreach ($earlier as $previous)
<p style="margin:12px 0 4px;font-size:14px;font-weight:700;color:#1F1A1E;">{{ __($previous->type->label()) }}: {{ __(':from → :to', ['from' => $previous->from_party->label(), 'to' => $previous->to_party->label()]) }}@if ($previous->remark_type) · {{ $previous->remark_type->label() }}@endif <span style="font-weight:400;color:#6B626B;font-size:13px;">· {{ $previous->senderName() }} · {{ $previous->created_at->format('j M Y') }}</span></p>
@if ($previous->body)
<p style="{{ $box }}">{{ $previous->body }}</p>
@endif
@endforeach
@endif
@endif

@if ($attached->isNotEmpty() || $notAttached->isNotEmpty())
<h2 style="{{ $sectionHeading }}">{{ __('Attachments') }}</h2>
@if ($attached->isNotEmpty())
<p style="margin:0;font-size:14px;color:#3B3339;">{{ __('Attached to this email:') }} {{ $attached->pluck('original_name')->join(', ') }}</p>
@endif
@if ($notAttached->isNotEmpty())
<p style="margin:8px 0 0;font-size:14px;color:#3B3339;">{{ __('Not attached (too large for one email):') }} {{ $notAttached->pluck('original_name')->join(', ') }}</p>
@endif
@endif

@if ($portalUrl)
<table cellpadding="0" cellspacing="0" role="presentation" style="margin-top:24px;">
<tr><td style="background-color:#875A7B;border-radius:10px;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:11px 20px;color:#FFFFFF;font-size:14px;font-weight:600;text-decoration:none;">{{ __('Reply in portal') }}</a></td></tr>
</table>
<p style="margin:16px 0 0;font-size:13px;color:#6B626B;">{{ __('Please reply through the portal so your response is recorded.') }}</p>
@else
<p style="margin:24px 0 0;font-size:13px;color:#6B626B;">{{ __('Reply to this email. The Admin Office records your reply in the portal.') }}</p>
@endif
</x-mail.gmail-layout>
