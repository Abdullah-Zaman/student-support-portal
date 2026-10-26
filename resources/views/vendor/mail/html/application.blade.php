@props(['number', 'subject' => null])
<table class="application-summary" width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="application-summary-content"><p class="application-number">{{ $number }}</p>@if (filled($subject))<p class="application-subject">{{ $subject }}</p>@endif</td></tr></table>
