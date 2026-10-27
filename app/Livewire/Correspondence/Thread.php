<?php

namespace App\Livewire\Correspondence;

use App\Actions\Correspondence\LogExternalReply;
use App\Actions\Correspondence\QueueCorrespondenceEmail;
use App\Actions\Correspondence\ReplyToCorrespondence;
use App\Concerns\ApplicationValidationRules;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * An application's staff correspondence as one timeline: forwards,
 * replies, remarks and logged Registrar replies, with email status and
 * "Seen". Opening it (and each 30-second refresh) marks messages to the
 * viewer's role as seen. Embedded in the Admin Officer's, Super Admin's
 * (read-only) and HOD/Dean's application views; never in a student view.
 */
class Thread extends Component
{
    use ApplicationValidationRules, WithFileUploads;

    public const BODY_MAX_LENGTH = 2000;

    #[Locked]
    public Application $application;

    public string $reply_to = '';

    public string $reply_body = '';

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $reply_attachments = [];

    /**
     * Idempotency key of the reply being written; a new one after each
     * send, so a double click posts it only once.
     */
    #[Locked]
    public string $reply_key = '';

    public string $external_body = '';

    /**
     * @var array<int, TemporaryUploadedFile>
     */
    public array $external_attachments = [];

    #[Locked]
    public string $external_key = '';

    public function mount(Application $application): void
    {
        $this->authorize('viewCorrespondence', $application);

        $this->application = $application;
        $this->reply_key = (string) Str::uuid();
        $this->external_key = (string) Str::uuid();

        $targets = $this->replyTargets();
        $this->reply_to = count($targets) === 1 ? $targets[0]->value : '';

        $this->markSeen();
    }

    /**
     * @return Collection<int, CorrespondenceMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->application->correspondence()
            ->with(['sender', 'recipient', 'author', 'seenBy', 'attachments'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Who this user may reply to on this application right now.
     *
     * @return list<CorrespondenceParty>
     */
    #[Computed]
    public function replyTargets(): array
    {
        $user = Auth::user();

        return array_values(array_filter(
            [CorrespondenceParty::AdminOfficer, CorrespondenceParty::Hod, CorrespondenceParty::Dean],
            fn (CorrespondenceParty $party) => $user->can('reply', [$this->application, $party]),
        ));
    }

    /**
     * Shown to anyone who can write here without a usable Gmail: their
     * messages are saved but wait until they connect it.
     */
    #[Computed]
    public function gmailNotice(): ?string
    {
        $user = Auth::user();
        $canWrite = $this->replyTargets() !== []
            || $user->can('remark', $this->application)
            || $user->can('logExternalReply', $this->application);

        if (! $canWrite || $user->hasConnectedGmail()) {
            return null;
        }

        return $user->googleAccount?->needs_reconnect
            ? __('Reconnect your Gmail. Your messages are saved in the portal and will be emailed automatically once you reconnect.')
            : __('Connect your Gmail. Your messages are saved in the portal and will be emailed from your own Gmail automatically once you connect it.');
    }

    /**
     * Polled every 30 seconds, and called after a forward or remark is
     * posted elsewhere on the page.
     */
    #[On('correspondence-posted')]
    public function refreshThread(): void
    {
        $this->markSeen();
        unset($this->messages, $this->replyTargets, $this->gmailNotice);
    }

    public function removeReplyAttachment(int $index): void
    {
        unset($this->reply_attachments[$index]);
        $this->reply_attachments = array_values($this->reply_attachments);
    }

    public function removeExternalAttachment(int $index): void
    {
        unset($this->external_attachments[$index]);
        $this->external_attachments = array_values($this->external_attachments);
    }

    public function reply(ReplyToCorrespondence $replyToCorrespondence): void
    {
        $validated = $this->validateWithFiles('reply_attachments', [
            'reply_to' => ['required', Rule::enum(CorrespondenceParty::class)],
            'reply_body' => ['required', 'string', 'max:'.self::BODY_MAX_LENGTH],
        ], [
            'reply_to.required' => __('Choose who you are replying to.'),
            'reply_body.required' => __('Write your reply.'),
        ]);

        $recipient = CorrespondenceParty::from($validated['reply_to']);

        $this->authorize('reply', [$this->application, $recipient]);

        $message = $replyToCorrespondence->handle(
            $this->application,
            Auth::user(),
            $recipient,
            $validated['reply_body'],
            $this->reply_key,
            $this->reply_attachments,
        );

        $this->reset('reply_body', 'reply_attachments');
        $this->reply_key = (string) Str::uuid();
        $this->refreshThread();

        Flux::toast(variant: 'success', text: $this->postedToast($message, __('Reply sent to the :party.', ['party' => $recipient->label()])));
    }

    public function logExternalReply(LogExternalReply $logExternalReply): void
    {
        $this->authorize('logExternalReply', $this->application);

        $validated = $this->validateWithFiles('external_attachments', [
            'external_body' => ['required', 'string', 'max:'.self::BODY_MAX_LENGTH],
        ], [
            'external_body.required' => __('Paste or summarize the Registrar\'s reply.'),
        ]);

        $logExternalReply->handle($this->application, Auth::user(), $validated['external_body'], $this->external_key, $this->external_attachments);

        $this->reset('external_body', 'external_attachments');
        $this->external_key = (string) Str::uuid();
        $this->refreshThread();

        $this->modal('log-external-reply')->close();
        Flux::toast(variant: 'success', text: __('Registrar reply logged.'));
    }

    public function retryEmail(int $messageId, QueueCorrespondenceEmail $queueCorrespondenceEmail): void
    {
        $message = $this->application->correspondence()->findOrFail($messageId);

        $this->authorize('retryEmail', $message);

        $queueCorrespondenceEmail->handle($message);
        $this->refreshThread();

        $message->refresh();

        match ($message->email_status) {
            CorrespondenceEmailStatus::Sent => Flux::toast(variant: 'success', text: __('Email sent.')),
            CorrespondenceEmailStatus::Failed => Flux::toast(variant: 'danger', text: (string) $message->email_error),
            default => Flux::toast(text: __('Email queued for sending.')),
        };
    }

    public function render(): View
    {
        return view('livewire.correspondence.thread');
    }

    /**
     * Messages addressed to the viewer's role that they did not send
     * themselves become "Seen" the first time it is opened.
     */
    private function markSeen(): void
    {
        $user = Auth::user();
        $party = CorrespondenceParty::forRole($user->staffRole());

        if ($party === null) {
            return;
        }

        $this->application->correspondence()
            ->where('to_party', $party)
            ->whereNull('seen_at')
            ->where(fn ($query) => $query->whereNull('from_user_id')->orWhere('from_user_id', '!=', $user->id))
            ->update(['seen_at' => now(), 'seen_by_id' => $user->id]);
    }

    /**
     * Validate a form with its up-to-three files; files that fail their
     * own rule are dropped from the list so the user can pick again.
     *
     * @param  array<string, mixed>  $rules
     * @param  array<string, string>  $messages
     * @return array<string, mixed>
     */
    private function validateWithFiles(string $filesProperty, array $rules, array $messages): array
    {
        $files = $this->{$filesProperty};
        $fileMessages = [];

        foreach ($this->attachmentMessages($files) as $key => $message) {
            $fileMessages[Str::replaceFirst('attachments.', $filesProperty.'.', $key)] = $message;
        }

        try {
            return $this->validate([
                ...$rules,
                $filesProperty => $this->attachmentsRules(),
                $filesProperty.'.*' => $this->attachmentRules(),
            ], [
                ...$messages,
                ...$fileMessages,
                $filesProperty.'.max' => __('You can attach up to 3 files.'),
            ]);
        } catch (ValidationException $exception) {
            foreach (array_keys($exception->validator->errors()->toArray()) as $key) {
                if (preg_match('/^'.preg_quote($filesProperty, '/').'\.(\d+)$/', (string) $key, $matches)) {
                    unset($files[(int) $matches[1]]);
                }
            }

            $this->{$filesProperty} = array_values($files);

            throw $exception;
        }
    }

    private function postedToast(CorrespondenceMessage $message, string $sentText): string
    {
        return $message->email_status === CorrespondenceEmailStatus::AwaitingGmail
            ? __('Saved. It will be emailed once you connect your Gmail.')
            : $sentText;
    }
}
