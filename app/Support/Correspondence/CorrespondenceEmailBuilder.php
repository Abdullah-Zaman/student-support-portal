<?php

namespace App\Support\Correspondence;

use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceType;
use App\Models\ApplicationAttachment;
use App\Models\CorrespondenceAttachment;
use App\Models\CorrespondenceMessage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

/**
 * Builds the email for one correspondence message. Every email of an
 * application uses the same subject, "[SC-2026-000124] {subject}", and
 * carries Message-ID / In-Reply-To / References headers so mail clients
 * group the conversation. A forward carries the full packet (student,
 * application, earlier correspondence and the application's files); a
 * reply or remark carries its own text and files. The sender's address
 * is set by the GmailSender.
 */
class CorrespondenceEmailBuilder
{
    public const LOGO_CID = 'superior-logo';

    /**
     * How many earlier Message-IDs go into References: enough for any
     * real conversation, short enough to keep the header small.
     */
    private const MAX_REFERENCES = 20;

    public function build(CorrespondenceMessage $message): Email
    {
        $message->loadMissing(['sender', 'recipient', 'attachments', 'application.student.user', 'application.student.program', 'application.category']);
        $application = $message->application;
        $isForward = $message->type === CorrespondenceType::Forward;

        $earlier = $application->correspondence()
            ->where('id', '<', $message->id)
            ->with(['sender', 'recipient'])
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
            'earlier' => $isForward ? $earlier : collect(),
            'attached' => $attached,
            'notAttached' => $notAttached,
            'portalUrl' => $this->portalUrl($message),
            'logo' => 'cid:'.self::LOGO_CID,
        ];

        $email = (new Email)
            ->to($this->recipientEmail($message))
            ->subject(self::subject($message))
            ->html(view('mail.gmail.correspondence', $data)->render())
            ->text(view('mail.gmail.correspondence-text', $data)->render())
            ->embedFromPath(public_path('images/superior-logo-white.png'), self::LOGO_CID, 'image/png');

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
     * The same for every email of the application, so Gmail keeps them
     * in one conversation.
     */
    public static function subject(CorrespondenceMessage $message): string
    {
        return '['.$message->application->application_no.'] '.$message->application->subject;
    }

    public function recipientEmail(CorrespondenceMessage $message): string
    {
        return $message->to_user_id !== null ? $message->recipient->email : (string) $message->to_email;
    }

    /**
     * Message-ID is this message's own id. In-Reply-To and References
     * point at the earlier emails of the thread that actually went out.
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
     * Where the recipient opens the thread in the portal (login
     * required). The Registrar has no portal account.
     */
    private function portalUrl(CorrespondenceMessage $message): ?string
    {
        $routeName = $message->to_party->applicationRouteName();

        return $routeName !== null ? route($routeName, $message->application).'#correspondence' : null;
    }
}
