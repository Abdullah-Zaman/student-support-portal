{!! $application->application_no !!} — {!! $application->subject !!}
{!! __($message->type->label()) !!}: {!! $message->senderName() !!} ({!! $message->from_party->label() !!}) → {!! $message->to_party->label() !!}
@if ($message->remark_type)

{!! $message->remark_type->label() !!}
@endif
@if ($message->body)

{!! $message->body !!}
@endif
@if ($isForward)

{!! __('Student') !!}: {!! $student->user->name !!} ({!! $student->registration_no !!})
{!! __('Program') !!}: {!! $student->program?->code !!} · {!! __('Batch') !!}: {!! $student->batch_label !!} · {!! __('Semester') !!}: {!! $application->semester_at_submission !!}
{!! __('Email') !!}: {!! $student->user->email !!}

{!! __('Category') !!}: {!! $application->category?->name !!}
{!! __('Submitted') !!}: {!! $application->created_at->format('j M Y, g:i A') !!}

{!! $application->body !!}
@if ($earlier->isNotEmpty())

{!! __('Earlier correspondence') !!}:
@foreach ($earlier as $previous)
- {!! __($previous->type->label()) !!}: {!! $previous->from_party->label() !!} → {!! $previous->to_party->label() !!} ({!! $previous->senderName() !!}){!! $previous->remark_type ? ' · '.$previous->remark_type->label() : '' !!}{!! $previous->body ? ': '.$previous->body : '' !!}
@endforeach
@endif
@endif
@if ($attached->isNotEmpty())

{!! __('Attached to this email:') !!} {!! $attached->pluck('original_name')->join(', ') !!}
@endif
@if ($notAttached->isNotEmpty())
{!! __('Not attached (too large for one email):') !!} {!! $notAttached->pluck('original_name')->join(', ') !!}
@endif
@if ($portalUrl)

{!! __('Reply in portal:') !!} {!! $portalUrl !!}
{!! __('Please reply through the portal so your response is recorded.') !!}
@else

{!! __('Reply to this email. The Admin Office records your reply in the portal.') !!}
@endif

© {{ date('Y') }} {!! __('SE Department, Superior University') !!}
