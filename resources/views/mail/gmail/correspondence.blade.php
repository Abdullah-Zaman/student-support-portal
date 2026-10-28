@php
    $paragraph = fn (?string $text) => nl2br(e((string) $text), false);
@endphp
<p>{{ __('Dear :name,', ['name' => $greetingName]) }}</p>
@if ($message->remark_type)
<p>{{ __('Remark: :type', ['type' => $message->remark_type->label()]) }}</p>
@endif
@if ($message->body)
<p>{!! $paragraph($message->body) !!}</p>
@endif
<p>{!! $paragraph($signature) !!}</p>
@if ($isForward)
<p>---------- {{ __('Forwarded application') }} ----------<br>
{{ __('From:') }} {{ $student->user->name }} ({{ $student->registration_no }}, {{ $student->program?->code }}, {{ $application->semester_at_submission !== null ? __('Semester :n', ['n' => $application->semester_at_submission]) : __('Alumni') }})<br>
{{ __('Date:') }} {{ $application->created_at->format('D, j M Y, g:i A') }}<br>
{{ __('Subject:') }} {{ $application->subject }}</p>
<p>{!! $paragraph($application->body) !!}</p>
@elseif ($quoted)
<p>{{ __('On :date, :name wrote:', ['date' => $quoted->created_at->format('D, j M Y, g:i A'), 'name' => $quoted->senderName()]) }}</p>
<blockquote style="margin:0 0 0 1em;padding-left:1em;border-left:1px solid #ccc;">{!! $paragraph($quoted->body) !!}</blockquote>
@endif
@if ($notAttached->isNotEmpty())
<p>{{ __('Not attached (too large for one email):') }} {{ $notAttached->pluck('original_name')->join(', ') }}</p>
@endif
<p>{{ __('Ref: :number', ['number' => $application->application_no]) }}@if ($portalUrl) · {{ __('Reply on the portal:') }} <a href="{{ $portalUrl }}">{{ $portalUrl }}</a>@endif</p>
