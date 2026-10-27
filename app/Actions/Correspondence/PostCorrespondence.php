<?php

namespace App\Actions\Correspondence;

use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\RemarkType;
use App\Jobs\SendCorrespondenceEmail;
use App\Models\Application;
use App\Models\CorrespondenceMessage;
use App\Models\User;
use App\Notifications\CorrespondenceReceived;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Records one message in an application's correspondence thread, stores
 * its files, notifies the recipient and queues the email from the
 * sender's own Gmail. The caller authorizes first.
 *
 * Idempotent: the same author and key always give the same message, so
 * a double click never creates (or emails) it twice. Without a usable
 * Gmail connection the message is still recorded and waits as
 * "Pending: connect Gmail to send".
 */
class PostCorrespondence
{
    /**
     * @param  array{type: CorrespondenceType, from_party: CorrespondenceParty, from_user_id: int|null, from_email?: string|null, to_party: CorrespondenceParty, to_user_id: int|null, to_email?: string|null, remark_type?: RemarkType|null, body: string|null}  $attributes
     * @param  array<int, UploadedFile>  $files
     * @param  (Closure(CorrespondenceMessage): void)|null  $alsoInTransaction  further writes that belong with a new message
     */
    public function handle(
        Application $application,
        User $author,
        array $attributes,
        string $idempotencyKey,
        array $files = [],
        ?Closure $alsoInTransaction = null,
    ): CorrespondenceMessage {
        $existing = $this->existing($author, $idempotencyKey);

        if ($existing !== null) {
            return $existing;
        }

        try {
            $message = DB::transaction(function () use ($application, $author, $attributes, $idempotencyKey, $files, $alsoInTransaction) {
                $message = CorrespondenceMessage::create([
                    ...$attributes,
                    'application_id' => $application->id,
                    'author_id' => $author->id,
                    'body' => $this->clean($attributes['body']),
                    'email_status' => $this->initialEmailStatus($attributes['type'], $author),
                    'rfc_message_id' => self::newRfcMessageId($application),
                    'idempotency_key' => $idempotencyKey,
                ]);

                foreach ($files as $file) {
                    $message->attachments()->create([
                        'application_id' => $application->id,
                        'uploaded_by' => $author->id,
                        'original_name' => $file->getClientOriginalName(),
                        'file_path' => $file->store('correspondence/'.$application->id, 'local'),
                        'mime_type' => (string) $file->getMimeType(),
                        'size' => (int) $file->getSize(),
                    ]);
                }

                if ($alsoInTransaction !== null) {
                    $alsoInTransaction($message);
                }

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->existing($author, $idempotencyKey) ?? throw new RuntimeException('Duplicate correspondence message could not be loaded.');
        }

        $recipients = $this->notifiableRecipients($application, $message)->reject(fn (User $user) => $user->is($author));
        Notification::send($recipients, new CorrespondenceReceived($application, $message));

        if ($message->email_status === CorrespondenceEmailStatus::Sending) {
            SendCorrespondenceEmail::dispatch($message);
        }

        return $message;
    }

    /**
     * The person a message to this party goes to: for the Admin Officer,
     * whoever last wrote to the sender's party on this application (or
     * the department's first Admin Officer); for the HOD/Dean, the active
     * one. Null when there is no one (or for the Registrar).
     */
    public function recipientFor(Application $application, CorrespondenceParty $to, CorrespondenceParty $from): ?User
    {
        return match ($to) {
            CorrespondenceParty::AdminOfficer => $this->lastAdminOfficerWritingTo($application, $from)
                ?? User::adminOfficersInDepartment($application->department_id)->first(),
            CorrespondenceParty::Hod => User::activeHodForDepartment($application->department_id),
            CorrespondenceParty::Dean => User::activeDeanForFaculty($application->department?->faculty_id),
            CorrespondenceParty::Registrar => null,
        };
    }

    public static function newRfcMessageId(Application $application): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'student-support-portal.local';

        return 'sc.'.$application->id.'.'.Str::lower((string) Str::ulid()).'@'.$host;
    }

    private function lastAdminOfficerWritingTo(Application $application, CorrespondenceParty $party): ?User
    {
        $lastMessage = $application->correspondence()
            ->where('from_party', CorrespondenceParty::AdminOfficer)
            ->where('to_party', $party)
            ->whereNotNull('from_user_id')
            ->latest('id')
            ->first();

        return $lastMessage !== null ? $lastMessage->sender : null;
    }

    private function existing(User $author, string $idempotencyKey): ?CorrespondenceMessage
    {
        return CorrespondenceMessage::query()
            ->where('author_id', $author->id)
            ->where('idempotency_key', $idempotencyKey)
            ->first();
    }

    private function initialEmailStatus(CorrespondenceType $type, User $author): CorrespondenceEmailStatus
    {
        if ($type === CorrespondenceType::LoggedReply) {
            return CorrespondenceEmailStatus::NotEmailed;
        }

        return $author->hasConnectedGmail()
            ? CorrespondenceEmailStatus::Sending
            : CorrespondenceEmailStatus::AwaitingGmail;
    }

    /**
     * Everyone holding the recipient's party: all the department's Admin
     * Officers, or the one HOD/Dean the message went to.
     *
     * @return Collection<int, User>
     */
    private function notifiableRecipients(Application $application, CorrespondenceMessage $message): Collection
    {
        if ($message->to_party === CorrespondenceParty::AdminOfficer) {
            return User::adminOfficersInDepartment($application->department_id)->toBase();
        }

        return collect([$message->recipient])->filter()->values();
    }

    private function clean(?string $body): ?string
    {
        return $body !== null && trim($body) !== '' ? trim($body) : null;
    }
}
