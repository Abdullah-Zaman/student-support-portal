@php
    $label = 'padding:6px 12px 6px 0;color:#6B626B;font-size:13px;white-space:nowrap;vertical-align:top;';
    $value = 'padding:6px 0;color:#1F1A1E;font-size:14px;vertical-align:top;';
    $sectionHeading = 'margin:24px 0 8px;font-size:13px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#875A7B;';
    $box = 'margin:0;padding:12px 14px;background-color:#F6F4F5;border-radius:10px;font-size:14px;line-height:1.6;color:#3B3339;white-space:pre-line;';
@endphp
<x-mail.gmail-layout :logo="$logo" :title="$application->application_no">
<p style="margin:0 0 6px;font-size:13px;color:#6B626B;">{{ __('Forwarded by :name (:role)', ['name' => $forward->sender->name, 'role' => $forward->from_role->label()]) }}</p>
<h1 style="margin:0;font-size:20px;line-height:1.3;color:#1F1A1E;">{{ $application->application_no }}</h1>
<p style="margin:4px 0 0;font-size:15px;color:#3B3339;">{{ $application->subject }}</p>

<h2 style="{{ $sectionHeading }}">{{ __('Note from :role', ['role' => $forward->from_role->label()]) }}</h2>
<p style="{{ $box }}">{{ $forward->note }}</p>

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

@if ($earlierForwards->isNotEmpty())
<h2 style="{{ $sectionHeading }}">{{ __('Earlier forwards and remarks') }}</h2>
@foreach ($earlierForwards as $earlier)
<p style="margin:12px 0 4px;font-size:14px;font-weight:700;color:#1F1A1E;">{{ __(':from → :to', ['from' => $earlier->from_role->label(), 'to' => $earlier->to_role->label()]) }} <span style="font-weight:400;color:#6B626B;font-size:13px;">· {{ $earlier->sender->name }} · {{ $earlier->created_at->format('j M Y') }}</span></p>
<p style="{{ $box }}">{{ $earlier->note }}</p>
@foreach ($earlier->remarks as $remark)
<p style="margin:8px 0 0 14px;padding-left:12px;border-left:2px solid #E7E2E5;font-size:14px;line-height:1.6;color:#3B3339;white-space:pre-line;"><strong style="color:#1F1A1E;">{{ $remark->type->label() }}</strong> · {{ $remark->author_role->label() }} · {{ $remark->author->name }}@if ($remark->body)
<br>{{ $remark->body }}@endif</p>
@endforeach
@endforeach
@endif

@if ($attached->isNotEmpty() || $notAttached->isNotEmpty())
<h2 style="{{ $sectionHeading }}">{{ __('Attachments') }}</h2>
@if ($attached->isNotEmpty())
<p style="margin:0;font-size:14px;color:#3B3339;">{{ __('Attached to this email:') }} {{ $attached->pluck('original_name')->join(', ') }}</p>
@endif
@if ($notAttached->isNotEmpty())
<p style="margin:8px 0 0;font-size:14px;color:#3B3339;">{{ __('Not attached (too large for one email):') }} {{ $notAttached->pluck('original_name')->join(', ') }}</p>
@if ($portalUrl)
<p style="margin:4px 0 0;font-size:14px;"><a href="{{ $portalUrl }}" style="color:#875A7B;font-weight:600;">{{ __('Open the application in the portal (login required)') }}</a></p>
@else
<p style="margin:4px 0 0;font-size:14px;color:#6B626B;">{{ __('Reply to this email to ask the sender for these files.') }}</p>
@endif
@endif
@endif

@if ($portalUrl)
<table cellpadding="0" cellspacing="0" role="presentation" style="margin-top:24px;">
<tr><td style="background-color:#875A7B;border-radius:10px;"><a href="{{ $portalUrl }}" style="display:inline-block;padding:11px 20px;color:#FFFFFF;font-size:14px;font-weight:600;text-decoration:none;">{{ __('Open in the portal') }}</a></td></tr>
</table>
@endif
</x-mail.gmail-layout>
