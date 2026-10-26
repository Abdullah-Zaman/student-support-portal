<?php

use App\Enums\MessageType;
use App\Models\Department;
use App\Notifications\AdminMessageReceived;
use App\Notifications\ApplicationInfoRequested;
use Symfony\Component\Mime\Email;

beforeEach(function () {
    createRoles();
});

/**
 * The last email the array mailer captured.
 */
function lastSentEmail(): Email
{
    /** @var Email $email */
    $email = app('mailer')->getSymfonyTransport()->messages()->last()->getOriginalMessage();

    return $email;
}

test('a system email embeds the logo inline once and puts the admin message above the button', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['application_no' => 'SC-2026-000124', 'subject' => 'Fee instalment request']);

    $student->notify(new AdminMessageReceived($application, "Please bring your fee slip.\nThe office is open till 3 PM."));

    $email = lastSentEmail();
    $html = (string) $email->getHtmlBody();
    $inlineParts = collect($email->getAttachments())->filter(fn ($part) => $part->getDisposition() === 'inline');

    expect($inlineParts)->toHaveCount(1)
        ->and($inlineParts->first()->getMediaSubtype())->toBe('png')
        ->and($html)->toContain('src="cid:')
        ->not->toContain('superior-logo-white.svg');

    expect($html)->toContain('Message from the Admin Office:', 'Please bring your fee slip.<br>The office is open till 3 PM.')
        ->and(strpos($html, 'Message from the Admin Office:'))->toBeLessThan(strpos($html, 'View application'))
        ->and(strpos($html, $application->application_no))->toBeLessThan(strpos($html, 'Fee instalment request'))
        ->and($html)->toContain('© '.date('Y').' SE Department, Superior University');
});

test('an info request shows the request text in the highlighted box', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);

    $student->notify(new ApplicationInfoRequested($application, MessageType::DocumentRequest, 'Upload your CNIC copy.'));

    expect((string) lastSentEmail()->getHtmlBody())
        ->toContain('Message from the Admin Office:', 'Upload your CNIC copy.');
});

test('student-supplied text in a system email is escaped', function () {
    $department = Department::factory()->create();
    $student = createStudentUser($department);
    $application = createApplicationInDepartment($department, $student);
    $application->update(['subject' => '<script>alert(1)</script>']);

    $student->notify(new AdminMessageReceived($application, '<b>bold</b>'));

    expect((string) lastSentEmail()->getHtmlBody())
        ->not->toContain('<script>alert(1)</script>', '<b>bold</b>')
        ->toContain('&lt;b&gt;bold&lt;/b&gt;');
});
