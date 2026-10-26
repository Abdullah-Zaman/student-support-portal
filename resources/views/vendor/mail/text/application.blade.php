@props(['number', 'subject' => null])
{{ $number }}
@if (filled($subject))
{{ $subject }}
@endif
