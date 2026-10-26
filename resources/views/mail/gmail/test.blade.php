<x-mail.gmail-layout :logo="$logo" :title="__('Gmail test email')">
<h1 style="margin:0 0 8px;font-size:20px;color:#1F1A1E;">{{ __('Your Gmail is connected') }}</h1>
<p style="margin:0;">{{ __('Hello :name, this test email was sent from your own Gmail through the Student Support Portal. Forwards you send from the portal will be emailed from this account.', ['name' => $user->name]) }}</p>
</x-mail.gmail-layout>
