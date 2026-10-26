@props(['logo' => null])
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<img src="{{ $logo ?? asset(\App\Support\MailLogo::PATH) }}" class="logo" alt="{{ config('app.name') }}" height="40" style="height: 40px; width: auto;">
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ __('SE Department, Superior University') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
