<?php

namespace App\Livewire\Correspondence;

use App\Actions\Correspondence\LogExternalReply;
use App\Actions\Correspondence\QueueCorrespondenceEmail;
use App\Actions\Correspondence\ReplyToCorrespondence;
use App\Concerns\ApplicationValidationRules;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\RoleName;
use App\Models\Application;
use App\Models\CorrespondenceConversation;
use App\Models\CorrespondenceMessage;
use App\Support\Correspondence\CorrespondenceInbox;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The "Conversations" panel of an application: the viewer's own private
 * staff conversations (list on the left, the selected chat on the right;
 * separate screens on mobile). Only conversations the viewer takes part
 * in are ever loaded, so another pair's messages cannot be reached even
 * by changing ids in a Livewire payload. Opening a conversation marks
 * messages to the viewer's role as seen. Embedded in the Admin Officer's
 * and the HOD/Dean's application views; never in a student's or the
 * Super Admin's.
 *
 * @property-read Collection<int, CorrespondenceConversation> $conversations
 * @property-read CorrespondenceConversation|null $selected
 * @property-read Collection<int, CorrespondenceMessage> $messages
 * @property-read array<int, array<mixed>|string> $forwardStatusLines
 * @property-read string|null $gmailNotice
 */
class Thread extends Component
{
    use ApplicationValidationRules, WithFileUploads;

    public const BODY_MAX_LENGTH = 2000;

    #[Locked]
    public Application $application;

    #[Locked]
    public ?int $selectedId = null;

    /**
     * On small screens: the chat instead of the conversation list.
     */
    public bool $showChat = false;

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

        $requested = request()->integer('conversation');
        $conversations = $this->conversations;

        $this->showChat = $requested > 0 && $conversations->contains('id', $requested);
        $this->selectedId = $this->showChat
            ? $requested
            : ($conversations->sortByDesc('unread_count')->first() ?? $conversations->first())?->id;

        $this->markSeen();
    }

    /**
     * The viewer's own conversations on this application, newest
     * activity first.
     *
     * @return Collection<int, CorrespondenceConversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        $user = Auth::user();
        $party = CorrespondenceParty::forRole($user->staffRole());

        return CorrespondenceConversation::query()
            ->visibleTo($user)
            ->where('application_id', $this->application->id)
            ->with('latestMessage')
            ->withCount(['messages as unread_count' => fn (Builder $query) => CorrespondenceInbox::constrainToUnread($query, $party, $user)])
            ->withMax('messages as latest_message_id', 'id')
            ->orderByDesc('latest_message_id')
            ->get();
    }

    #[Computed]
    public function selected(): ?CorrespondenceConversation
    {
        return $this->conversations->firstWhere('id', $this->selectedId);
    }

    /**
     * @return Collection<int, CorrespondenceMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        if ($this->selected === null) {
            return new Collection;
        }

        return $this->selected->messages()
            ->with(['sender', 'recipient', 'author', 'attachments'])
            ->orderBy('id')
            ->get();
    }

    /**
     * What the Admin Officer may know about forwards between others,
     * without any content: "HOD forwarded this to the Dean on 27 Oct".
     *
     * @return array<int, array<mixed>|string>
     */
    #[Computed]
    public function forwardStatusLines(): array
    {
        if (Auth::user()->staffRole() !== RoleName::AdminOfficer) {
            return [];
        }

        return CorrespondenceMessage::query()
            ->where('application_id', $this->application->id)
            ->where('type', CorrespondenceType::Forward)
            ->where('from_party', '!=', CorrespondenceParty::AdminOfficer)
            ->orderBy('id')
            ->get(['from_party', 'to_party', 'created_at'])
            ->map(fn (CorrespondenceMessage $forward) => __(':from forwarded this to the :to on :date', [
                'from' => $forward->from_party->label(),
                'to' => $forward->to_party->label(),
                'date' => $forward->created_at->format('j M'),
            ]))
            ->all();
    }

    /**
     * Shown to anyone who can write here without a usable Gmail: their
     * messages are saved but wait until they connect it.
     */
    #[Computed]
    public function gmailNotice(): ?string
    {
        $user = Auth::user();
        $canWrite = $this->selected !== null
            && ($user->can('reply', $this->selected) || $user->can('logExternalReply', $this->selected));

        if (! $canWrite || $user->hasConnectedGmail()) {
            return null;
        }

        return $user->googleAccount?->needs_reconnect
            ? __('Reconnect your Gmail. Your messages are saved in the portal and will be emailed automatically once you reconnect.')
            : __('Connect your Gmail. Your messages are saved in the portal and will be emailed from your own Gmail automatically once you connect it.');
    }

    public function selectConversation(int $conversationId): void
    {
        $conversation = $this->conversations->firstWhere('id', $conversationId);

        abort_if($conversation === null, 404);
        $this->authorize('view', $conversation);

        $this->selectedId = $conversation->id;
        $this->showChat = true;
        $this->reset('reply_body', 'reply_attachments');
        $this->refreshThread();
    }

    public function backToList(): void
    {
        $this->showChat = false;
    }

    /**
     * Polled every 30 seconds, and called after a forward or remark is
     * posted elsewhere on the page.
     */
    #[On('correspondence-posted')]
    public function refreshThread(): void
    {
        $this->forgetComputed();

        if ($this->selectedId === null && $this->conversations->isNotEmpty()) {
            $this->selectedId = $this->conversations->first()->id;
            unset($this->selected);
        }

        $this->markSeen();
        $this->forgetComputed();
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
        $conversation = $this->selected;

        abort_if($conversation === null, 404);
        $this->authorize('reply', $conversation);

        $validated = $this->validateWithFiles('reply_attachments', [
            'reply_body' => ['required', 'string', 'max:'.self::BODY_MAX_LENGTH],
        ], [
            'reply_body.required' => __('Write your message.'),
        ]);

        $message = $replyToCorrespondence->handle(
            $conversation,
            Auth::user(),
            $validated['reply_body'],
            $this->reply_key,
            $this->reply_attachments,
        );

        $this->reset('reply_body', 'reply_attachments');
        $this->reply_key = (string) Str::uuid();
        $this->refreshThread();

        Flux::toast(variant: 'success', text: $this->postedToast($message, __('Message sent.')));
    }

    public function logExternalReply(LogExternalReply $logExternalReply): void
    {
        $conversation = $this->selected;

        abort_if($conversation === null, 404);
        $this->authorize('logExternalReply', $conversation);

        $validated = $this->validateWithFiles('external_attachments', [
            'external_body' => ['required', 'string', 'max:'.self::BODY_MAX_LENGTH],
        ], [
            'external_body.required' => __('Paste or summarize the Registrar\'s reply.'),
        ]);

        $logExternalReply->handle($conversation, Auth::user(), $validated['external_body'], $this->external_key, $this->external_attachments);

        $this->reset('external_body', 'external_attachments');
        $this->external_key = (string) Str::uuid();
        $this->refreshThread();

        $this->modal('log-external-reply')->close();
        Flux::toast(variant: 'success', text: __('Registrar reply logged.'));
    }

    public function retryEmail(int $messageId, QueueCorrespondenceEmail $queueCorrespondenceEmail): void
    {
        $message = CorrespondenceMessage::query()
            ->visibleTo(Auth::user())
            ->where('application_id', $this->application->id)
            ->findOrFail($messageId);

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

    private function forgetComputed(): void
    {
        unset($this->conversations, $this->selected, $this->messages, $this->forwardStatusLines, $this->gmailNotice);
    }

    /**
     * Messages in the open conversation addressed to the viewer's role
     * that they did not send themselves become "Seen".
     */
    private function markSeen(): void
    {
        $user = Auth::user();
        $party = CorrespondenceParty::forRole($user->staffRole());

        if ($party === null || $this->selected === null) {
            return;
        }

        CorrespondenceInbox::constrainToUnread(CorrespondenceMessage::query()->where('conversation_id', $this->selected->id), $party, $user)
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
