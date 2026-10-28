{!! __('Dear :name,', ['name' => $greetingName]) !!}
@if ($message->remark_type)

{!! __('Remark: :type', ['type' => $message->remark_type->label()]) !!}
@endif
@if ($message->body)

{!! $message->body !!}
@endif

{!! $signature !!}
@if ($isForward)

---------- {!! __('Forwarded application') !!} ----------
{!! __('From:') !!} {!! $student->user->name !!} ({!! $student->registration_no !!}, {!! $student->program?->code !!}, {!! __('Semester :n', ['n' => $application->semester_at_submission]) !!})
{!! __('Date:') !!} {!! $application->created_at->format('D, j M Y, g:i A') !!}
{!! __('Subject:') !!} {!! $application->subject !!}

{!! $application->body !!}
@elseif ($quoted)

{!! __('On :date, :name wrote:', ['date' => $quoted->created_at->format('D, j M Y, g:i A'), 'name' => $quoted->senderName()]) !!}
@foreach (preg_split('/\R/', (string) $quoted->body) as $line)
> {!! $line !!}
@endforeach
@endif
@if ($notAttached->isNotEmpty())

{!! __('Not attached (too large for one email):') !!} {!! $notAttached->pluck('original_name')->join(', ') !!}
@endif

{!! __('Ref: :number', ['number' => $application->application_no]) !!}@if ($portalUrl) · {!! __('Reply on the portal:') !!} {!! $portalUrl !!}@endif
