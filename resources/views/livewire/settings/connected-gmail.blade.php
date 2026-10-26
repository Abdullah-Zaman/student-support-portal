<section class="flex flex-col gap-4" data-test="connected-gmail">
    <div>
        <h3 class="m-0 text-[15px] font-bold text-ink">{{ __('Connected Gmail') }}</h3>
        <p class="m-0 mt-1 text-sm text-muted">{{ __('Forwards you send are emailed from your own Gmail. The portal can only send email, never read it, and never stores your Google password.') }}</p>
    </div>

    @if (session('gmail_status'))
        <p class="m-0 rounded-[10px] bg-green-50 px-3.5 py-2.5 text-[13px] text-green-800">{{ session('gmail_status') }}</p>
    @endif

    @if (session('gmail_error'))
        <p class="m-0 rounded-[10px] bg-red-50 px-3.5 py-2.5 text-[13px] text-red-800" data-test="gmail-error">{{ session('gmail_error') }}</p>
    @endif

    @if ($this->account)
        <div class="flex flex-wrap items-center gap-2">
            <flux:icon icon="envelope" variant="micro" class="size-4 text-plum" />
            <span class="text-sm font-semibold text-ink">{{ $this->account->google_email }}</span>
            @if ($this->account->needs_reconnect)
                <flux:badge color="amber" size="sm" icon="exclamation-triangle">{{ __('Reconnect needed') }}</flux:badge>
            @else
                <flux:badge color="green" size="sm" icon="check-circle">{{ __('Connected') }}</flux:badge>
            @endif
        </div>
        <p class="m-0 text-[13px] text-subtle">{{ __('Connected on :date', ['date' => $this->account->connected_at->format('j M Y, g:i A')]) }}</p>

        @if ($this->account->needs_reconnect)
            <p class="m-0 rounded-[10px] bg-[#FFF1D6] px-3.5 py-2.5 text-[13px] text-[#7A4A00]">{{ __('Google no longer accepts this connection (access was revoked or has expired). Reconnect to keep emailing forwards.') }}</p>
        @endif

        <div class="flex flex-wrap gap-2">
            @if ($this->account->needs_reconnect)
                <flux:button variant="primary" size="sm" icon="arrow-path" :href="route('google.connect')">{{ __('Reconnect Gmail') }}</flux:button>
            @else
                <flux:button size="sm" icon="paper-airplane" wire:click="sendTestEmail" wire:loading.attr="disabled" data-test="send-test-email">{{ __('Send test email') }}</flux:button>
            @endif

            <x-confirm-modal
                name="confirm-disconnect-gmail"
                :heading="__('Disconnect Gmail?')"
                :text="__('The portal will revoke its access at Google and delete the stored tokens. You will not be able to forward applications until you connect again.')"
                :confirmLabel="__('Disconnect')"
                confirmAction="disconnect"
            >
                <x-slot:trigger>
                    <flux:button size="sm" variant="ghost" icon="link-slash">{{ __('Disconnect') }}</flux:button>
                </x-slot:trigger>
            </x-confirm-modal>
        </div>
    @else
        <p class="m-0 text-sm text-ink-soft">{{ __('Not connected. Connect the Google account of :email to forward applications.', ['email' => auth()->user()->email]) }}</p>
        <flux:button variant="primary" size="sm" icon="link" class="self-start" :href="route('google.connect')" data-test="connect-gmail">{{ __('Connect Gmail') }}</flux:button>
    @endif
</section>
