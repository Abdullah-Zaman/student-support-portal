<?php

namespace App\Support\Forwarding;

use App\Enums\ForwardTarget;
use App\Models\ApplicationAttachment;
use App\Models\ApplicationForward;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Mime\Email;

/**
 * Builds the email for one forward: application details, student info,
 * the forward note, the earlier forward/remark chain and the application's
 * attachments (up to the configured total size; the rest are listed by
 * name). The sender's address is set by the GmailSender.
 */
class ForwardEmailBuilder
{
    public const LOGO_CID = 'superior-logo';

    public function build(ApplicationForward $forward): Email
    {
        $forward->loadMissing(['sender', 'recipient', 'application.student.user', 'application.student.program', 'application.category']);
        $application = $forward->application;

        $earlierForwards = $application->forwards()
            ->where('id', '<', $forward->id)
            ->with(['sender', 'remarks.author'])
            ->orderBy('id')
            ->get();

        [$attached, $notAttached] = $this->splitAttachments($application->attachments()->orderBy('id')->get());

        $data = [
            'forward' => $forward,
            'application' => $application,
            'student' => $application->student,
            'earlierForwards' => $earlierForwards,
            'attached' => $attached,
            'notAttached' => $notAttached,
            'portalUrl' => $this->portalUrl($forward),
            'logo' => 'cid:'.self::LOGO_CID,
        ];

        $email = (new Email)
            ->to($this->recipientEmail($forward))
            ->subject(__(':prefix Forwarded: :number — :subject', [
                'prefix' => config('mail.subject_prefix'),
                'number' => $application->application_no,
                'subject' => $application->subject,
            ]))
            ->html(view('mail.gmail.forward', $data)->render())
            ->text(view('mail.gmail.forward-text', $data)->render())
            ->embedFromPath(public_path('images/superior-logo-white.png'), self::LOGO_CID, 'image/png');

        foreach ($attached as $attachment) {
            $email->attach(
                Storage::disk('local')->get($attachment->file_path),
                $attachment->original_name,
                $attachment->mime_type,
            );
        }

        return $email;
    }

    public function recipientEmail(ApplicationForward $forward): string
    {
        return $forward->to_role === ForwardTarget::Registrar
            ? (string) $forward->to_email
            : (string) $forward->recipient?->email;
    }

    /**
     * Attach files in order while the running total stays within the
     * limit; anything else (including missing files) is only listed.
     *
     * @param  Collection<int, ApplicationAttachment>  $attachments
     * @return array{0: Collection<int, ApplicationAttachment>, 1: Collection<int, ApplicationAttachment>}
     */
    private function splitAttachments(Collection $attachments): array
    {
        $limit = (int) config('google.forward_attachment_limit_bytes');
        $total = 0;
        $attached = collect();
        $notAttached = collect();

        foreach ($attachments as $attachment) {
            $fits = $total + $attachment->size <= $limit
                && Storage::disk('local')->exists($attachment->file_path);

            if ($fits) {
                $total += $attachment->size;
                $attached->push($attachment);
            } else {
                $notAttached->push($attachment);
            }
        }

        return [$attached, $notAttached];
    }

    /**
     * Where the recipient opens the application in the portal (login
     * required). The Registrar has no portal account.
     */
    private function portalUrl(ApplicationForward $forward): ?string
    {
        return match ($forward->to_role) {
            ForwardTarget::Hod => route('hod.applications.show', $forward->application),
            ForwardTarget::Dean => route('dean.applications.show', $forward->application),
            ForwardTarget::Registrar => null,
        };
    }
}
