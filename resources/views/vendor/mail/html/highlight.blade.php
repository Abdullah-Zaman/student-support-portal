{{-- Kept on one line with newlines turned into <br>: a blank line would end the HTML block and let Markdown parse the body. --}}
@props(['title', 'body'])
<table class="highlight" width="100%" cellpadding="0" cellspacing="0" role="presentation"><tr><td class="highlight-content"><p class="highlight-title">{{ $title }}</p><p class="highlight-body">{!! str_replace(["\r\n", "\r", "\n"], '<br>', e($body)) !!}</p></td></tr></table>
