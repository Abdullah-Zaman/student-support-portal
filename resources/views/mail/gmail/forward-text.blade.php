{!! __('Forwarded by :name (:role)', ['name' => $forward->sender->name, 'role' => $forward->from_role->label()]) !!}

{!! $application->application_no !!}
{!! $application->subject !!}

{!! __('Note from :role', ['role' => $forward->from_role->label()]) !!}:
{!! $forward->note !!}

{!! __('Student') !!}: {!! $student->user->name !!} ({!! $student->registration_no !!})
{!! __('Program') !!}: {!! $student->program?->code !!} · {!! __('Batch') !!}: {!! $student->batch_label !!} · {!! __('Semester') !!}: {!! $application->semester_at_submission !!}
{!! __('Email') !!}: {!! $student->user->email !!}

{!! __('Category') !!}: {!! $application->category?->name !!}
{!! __('Submitted') !!}: {!! $application->created_at->format('j M Y, g:i A') !!}

{!! $application->body !!}
@if ($earlierForwards->isNotEmpty())

{!! __('Earlier forwards and remarks') !!}:
@foreach ($earlierForwards as $earlier)
- {!! $earlier->from_role->label() !!} → {!! $earlier->to_role->label() !!} ({!! $earlier->sender->name !!}): {!! $earlier->note !!}
@foreach ($earlier->remarks as $remark)
  · {!! $remark->type->label() !!} — {!! $remark->author_role->label() !!} {!! $remark->author->name !!}{!! $remark->body ? ': '.$remark->body : '' !!}
@endforeach
@endforeach
@endif
@if ($attached->isNotEmpty())

{!! __('Attached to this email:') !!} {!! $attached->pluck('original_name')->join(', ') !!}
@endif
@if ($notAttached->isNotEmpty())
{!! __('Not attached (too large for one email):') !!} {!! $notAttached->pluck('original_name')->join(', ') !!}
@endif
@if ($portalUrl)

{!! __('Open in the portal (login required):') !!} {!! $portalUrl !!}
@endif

© {{ date('Y') }} {!! __('SE Department, Superior University') !!}
