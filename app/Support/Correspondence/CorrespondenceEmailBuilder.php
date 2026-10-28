<?php

namespace App\Support\Correspondence;

use App\Actions\Forwarding\ForwardApplication;
use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Models\ApplicationAttachment;
use App\Models\CorrespondenceAttachment;
use App\Models\CorrespondenceMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

/**
 * Builds the email for one correspondence message so it reads like a
 * personally written one: plain HTML plus a plain-text part, no logo or
 * styling. A forward is "Fwd: {subject} (SC-…)" with the sender's note,
 * their signature and the forwarded application (the student's own
 * files attached); a reply or remark is "Re: …" with the message, the
 * signature and the previous message of the same conversation quoted.
 * Only this conversation's messages are ever used, never another one's.
 * Message-ID / In-Reply-To / References follow the conversation. The
 * sender's address is set by the GmailSender.
 */
class CorrespondenceEmailBuilder
{
    /**
     * Content-ID of the logo in the Gmail test email (the correspondence
     * emails themselves carry no logo).
     */
    public const LOGO_CID = 'superior-logo';

    /**
     * How many earlier Message-IDs go into References: enough for any
     * real conversation, short enough to keep the header small.
     */
    private const MAX_REFERENCES = 20;

    public function build(CorrespondenceMessage $message): Email
    {
        $message->loadMissing(['sender', 'recipient', 'attachments', 'application.student.user', 'application.student.program']);
        $application = $message->application;
        $isForward = $message->type === CorrespondenceType::Forward;

        $earlier = CorrespondenceMessage::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('id', '<', $message->id)
            ->with('sender')
            ->orderBy('id')
            ->get();

        $files = $isForward
            ? $application->attachments()->orderBy('id')->get()
            : $message->attachments->sortBy('id')->values();

        [$attached, $notAttached] = $this->splitAttachments($files);

        $data = [
            'message' => $message,
            'application' => $application,
            'student' => $application->student,
            'isForward' => $isForward,
            'quoted' => $isForward ? null : $earlier->last(),
            'signature' => $message->sender?->emailSignature() ?? '',
            'greetingName' => $message->recipientName(),
            'notAttached' => $notAttached,
            'portalUrl' => $this->portalUrl($message),
        ];

        $email = (new Email)
            ->to($this->recipientEmail($message))
            ->subject(self::subject($message))
            ->html(view('mail.gmail.correspondence', $data)->render())
            ->text(view('mail.gmail.correspondence-text', $data)->render());

        $this->addThreadingHeaders($email, $message, $earlier);

        foreach ($attached as $attachment) {
            $email->attach(
                Storage::disk('local')->get($attachment->file_path),
                $attachment->original_name,
                $attachment->mime_type,
            );
        }

        return $email;
    }

    /**
     * "Fwd: …" for a forward, "Re: …" for everything after it.
     */
    public static function subject(CorrespondenceMessage $message): string
    {
        $prefix = $message->type === CorrespondenceType::Forward ? 'Fwd: ' : 'Re: ';

        return $prefix.$message->application->subject.' ('.$message->application->application_no.')';
    }

    /**
     * A portal user's mail_to_email (or login email). The Registrar's
     * address is the current forwarding.registrar_email setting, read at
     * send time, so a message that waited (pending Gmail, retry) never
     * goes to an outdated address; the stored one is the fallback.
     */
    public function recipientEmail(CorrespondenceMessage $message): string
    {
        if ($message->to_user_id !== null) {
            return $message->recipient->mail_to_email ?? $message->recipient->email;
        }

        if ($message->to_party === CorrespondenceParty::Registrar) {
            return ForwardApplication::registrarEmail() ?? (string) $message->to_email;
        }

        return (string) $message->to_email;
    }

    /**
     * Message-ID is this message's own id. In-Reply-To and References
     * point at the earlier emails of the conversation that went out.
     *
     * @param  Collection<int, CorrespondenceMessage>  $earlier
     */
    private function addThreadingHeaders(Email $email, CorrespondenceMessage $message, Collection $earlier): void
    {
        $headers = $email->getHeaders();

        if ($message->rfc_message_id !== null) {
            $headers->addIdHeader('Message-ID', $message->rfc_message_id);
        }

        $references = $earlier
            ->filter(fn (CorrespondenceMessage $previous) => $previous->rfc_message_id !== null && $previous->email_status === CorrespondenceEmailStatus::Sent)
            ->pluck('rfc_message_id')
            ->take(-self::MAX_REFERENCES)
            ->values()
            ->all();

        if ($references !== []) {
            $headers->addIdHeader('In-Reply-To', end($references));
            $headers->addIdHeader('References', $references);
        }
    }

    /**
     * Attach files in order while the running total stays within the
     * limit; anything else (including missing files) is only listed.
     *
     * @param  iterable<ApplicationAttachment|CorrespondenceAttachment>  $files
     * @return array{0: Collection<int, ApplicationAttachment|CorrespondenceAttachment>, 1: Collection<int, ApplicationAttachment|CorrespondenceAttachment>}
     */
    private function splitAttachments(iterable $files): array
    {
        $limit = (int) config('google.forward_attachment_limit_bytes');
        $total = 0;
        $attached = collect();
        $notAttached = collect();

        foreach ($files as $file) {
            $fits = $total + $file->size <= $limit
                && Storage::disk('local')->exists($file->file_path);

            if ($fits) {
                $total += $file->size;
                $attached->push($file);
            } else {
                $notAttached->push($file);
            }
        }

        return [$attached, $notAttached];
    }

    /**
     * Where the recipient replies in the portal (login required). The
     * Registrar has no portal account.
     */
    private function portalUrl(CorrespondenceMessage $message): ?string
    {
        $routeName = $message->to_party->applicationRouteName();

        return $routeName !== null
            ? route($routeName, $message->application).'?conversation='.$message->conversation_id.'#correspondence'
            : null;
    }
}
