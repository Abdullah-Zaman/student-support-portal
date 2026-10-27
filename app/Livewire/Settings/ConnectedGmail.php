<?php

namespace App\Livewire\Settings;

use App\Models\GoogleAccount;
use App\Support\Correspondence\CorrespondenceEmailBuilder;
use App\Support\Gmail\GmailNotConnected;
use App\Support\Gmail\GmailSender;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Symfony\Component\Mime\Email;

/**
 * The "Connected Gmail" card on a staff member's profile: connect,
 * disconnect (tokens revoked at Google and deleted) and a test email.
 */
class ConnectedGmail extends Component
{
    public function mount(): void
    {
        $this->authorize('manage-gmail');
    }

    #[Computed]
    public function account(): ?GoogleAccount
    {
        return Auth::user()->googleAccount()->first();
    }

    public function disconnect(GmailSender $gmail): void
    {
        $this->authorize('manage-gmail');

        $user = Auth::user();
        $account = $user->googleAccount()->first();

        if ($account !== null) {
            $gmail->revoke($account);
            $account->delete();

            activity()
                ->causedBy($user)
                ->withProperties(['google_email' => $account->google_email])
                ->log('gmail disconnected');
        }

        unset($this->account);
        $this->modal('confirm-disconnect-gmail')->close();
        Flux::toast(variant: 'success', text: __('Gmail disconnected. Its access was revoked at Google.'));
    }

    public function sendTestEmail(GmailSender $gmail): void
    {
        $this->authorize('manage-gmail');

        $user = Auth::user();
        $account = $user->googleAccount()->first();

        if ($account === null) {
            Flux::toast(variant: 'danger', text: __('Connect your Gmail first.'));

            return;
        }

        $throttleKey = 'gmail-test-email:'.$user->id;

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            Flux::toast(variant: 'danger', text: __('Too many test emails. Try again in :minutes minutes.', [
                'minutes' => (int) ceil(RateLimiter::availableIn($throttleKey) / 60),
            ]));

            return;
        }

        RateLimiter::hit($throttleKey, 600);

        $data = ['user' => $user, 'logo' => 'cid:'.CorrespondenceEmailBuilder::LOGO_CID];
        $email = (new Email)
            ->to($account->google_email)
            ->subject(config('mail.subject_prefix').' '.__('Gmail test email'))
            ->html(view('mail.gmail.test', $data)->render())
            ->text(__('Your Gmail is connected. Forwards you send from the portal will be emailed from this account.'))
            ->embedFromPath(public_path('images/superior-logo-white.png'), CorrespondenceEmailBuilder::LOGO_CID, 'image/png');

        try {
            $gmail->send($user, $email);
        } catch (GmailNotConnected $exception) {
            unset($this->account);
            Flux::toast(variant: 'danger', text: $exception->getMessage());

            return;
        } catch (ConnectionException|RequestException) {
            Flux::toast(variant: 'danger', text: __('Gmail could not send the test email. Try again, or disconnect and connect again.'));

            return;
        }

        activity()
            ->causedBy($user)
            ->performedOn($account)
            ->log('gmail test email sent');

        Flux::toast(variant: 'success', text: __('Test email sent to :email.', ['email' => config('mail.always_to') && ! app()->isProduction() ? config('mail.always_to') : $account->google_email]));
    }

    public function render(): View
    {
        return view('livewire.settings.connected-gmail');
    }
}
