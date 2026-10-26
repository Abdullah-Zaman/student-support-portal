<?php

namespace Database\Seeders;

use App\Enums\ApplicationEventType;
use App\Enums\ApplicationStatus;
use App\Enums\MessageType;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Announcement;
use App\Models\Application;
use App\Models\ApplicationCategory;
use App\Models\ApplicationEvent;
use App\Models\ApplicationMessage;
use App\Models\Department;
use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Sample students and applications for UI testing.
 *
 * Not called from DatabaseSeeder — run explicitly with:
 *   php artisan db:seed --class=DemoSeeder
 *
 * To refresh an already-seeded database's demo rows in place (without
 * creating new ones or touching anything else), use `php artisan demo:refresh`.
 *
 * @phpstan-type MessageDef array{type: string, from: string, body: string}
 * @phpstan-type AppDef array{category: string, status: string, priority: string, subject: string, body: string, resolution_note?: string, rejection_reason?: string, messages?: array<int, MessageDef>}
 * @phpstan-type StudentDef array{name: string, program_code: string, batch: string, sequence: int, current_semester: int, applications: array<int, AppDef>}
 */
class DemoSeeder extends Seeder
{
    /**
     * Statuses still "in flight" — their application was submitted this
     * same semester, so semester_at_submission matches current_semester.
     * Concluded ones (resolved/closed/rejected) are treated as slightly
     * older, having been submitted a semester earlier.
     */
    private const ACTIVE_STATUSES = ['submitted', 'under_review', 'info_required', 'in_progress'];

    public function run(): void
    {
        $department = Department::query()->where('code', 'SE')->firstOrFail();
        $categories = ApplicationCategory::query()->where('department_id', $department->id)->get()->keyBy('name');
        $adminUserId = User::query()->where('department_id', $department->id)
            ->role(RoleName::AdminOfficer->value)->value('id');

        $globalIndex = 0;

        foreach (self::demoStudents() as $def) {
            $registrationNo = self::registrationNo($def);

            $user = User::query()->create([
                'name' => $def['name'],
                'email' => strtolower($registrationNo).config('students.email_domain'),
                'password' => config('seeding.default_password'),
                'department_id' => $department->id,
                'status' => UserStatus::Active,
                'email_verified_at' => now(),
            ]);
            $user->syncRoles([RoleName::Student->value]);

            $student = Student::query()->create([
                'user_id' => $user->id,
                'registration_no' => $registrationNo,
                'program_id' => Program::query()->where('code', $def['program_code'])->value('id'),
                'current_semester' => $def['current_semester'],
                'batch' => $def['batch'],
            ]);

            foreach ($def['applications'] as $appDef) {
                $attributes = self::applicationAttributes($appDef, $globalIndex, $student->current_semester);

                $application = Application::createWithApplicationNumber(array_merge(
                    [
                        'student_id' => $student->id,
                        'department_id' => $department->id,
                        'category_id' => $categories[$appDef['category']]->id,
                    ],
                    $attributes,
                ));

                // created_at/updated_at aren't mass-assignable, so backdating
                // them (to match their application_events) needs forceFill.
                $application->forceFill([
                    'created_at' => $attributes['created_at'],
                    'updated_at' => $attributes['updated_at'],
                ])->save();

                self::createEvents($application, $appDef, $globalIndex, $user->id, $adminUserId);

                if (! empty($appDef['messages'])) {
                    self::createMessages($application, $appDef['messages'], $globalIndex, $user->id, $adminUserId);
                }

                $globalIndex++;
            }
        }

        self::createAnnouncements($department->id, $adminUserId);
    }

    /**
     * The realistic demo dataset, shared with `demo:refresh` so a fresh
     * seed and an in-place refresh always produce the same kind of data.
     *
     * @return array<int, StudentDef>
     */
    public static function demoStudents(): array
    {
        return [
            [
                'name' => 'Hamza Ali',
                'program_code' => 'BSSE',
                'batch' => 'F22',
                'sequence' => 101,
                'current_semester' => 7,
                'applications' => [
                    [
                        'category' => 'Fee Issue',
                        'status' => 'resolved',
                        'priority' => 'high',
                        'subject' => 'Fee challan not updated after payment',
                        'body' => 'I paid my semester fee challan through Bank Al Habib on 2 October but the portal still shows it as unpaid. Please update my fee status so I am not marked as a defaulter.',
                        'resolution_note' => 'Payment verified with the bank statement and the portal has been updated. The fee status now shows as paid.',
                    ],
                    [
                        'category' => 'Attendance Issue',
                        'status' => 'resolved',
                        'priority' => 'normal',
                        'subject' => 'Marked absent in OOP lecture while present',
                        'body' => 'I was present in the Object Oriented Programming lecture on 6 October but the attendance sheet shows me as absent. Several classmates can confirm I was there.',
                        'resolution_note' => 'Checked with the course instructor and the attendance record has been corrected.',
                    ],
                    [
                        'category' => 'LMS/Portal Issue',
                        'status' => 'rejected',
                        'priority' => 'normal',
                        'subject' => 'Cannot access recorded lecture videos on LMS',
                        'body' => 'The recorded lectures for Database Systems are not loading on the LMS portal. I get a blank page every time I try to open them.',
                        'rejection_reason' => 'This is a known, temporary LMS outage already reported to the vendor. No department-level action is needed; please retry after tonight\'s maintenance window ends.',
                    ],
                    [
                        'category' => 'Registration Issue',
                        'status' => 'info_required',
                        'priority' => 'normal',
                        'subject' => 'Course withdrawal request missing approval',
                        'body' => 'I submitted a course withdrawal request for Linear Algebra two weeks ago but it still shows as pending on the portal.',
                        'messages' => [
                            ['type' => 'info_request', 'from' => 'admin', 'body' => 'Please upload a copy of your signed withdrawal form so we can verify it against our records.'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Abdullah Khan',
                'program_code' => 'BSSE',
                'batch' => 'F23',
                'sequence' => 102,
                'current_semester' => 5,
                'applications' => [
                    [
                        'category' => 'Examination Issue',
                        'status' => 'under_review',
                        'priority' => 'high',
                        'subject' => 'Exam result not updated on ERP for Calculus',
                        'body' => 'My result for the Calculus-II midterm exam has not appeared on the ERP even though the exam was conducted three weeks ago. My classmates have already received their marks.',
                        'messages' => [
                            ['type' => 'message', 'from' => 'admin', 'body' => 'We have escalated this to the Examination Department and are waiting for their confirmation. We will update you as soon as we hear back.'],
                        ],
                    ],
                    [
                        'category' => 'Academic Issue',
                        'status' => 'closed',
                        'priority' => 'urgent',
                        'subject' => 'Course registration for FYP-II not allowed',
                        'body' => 'The ERP is not letting me register for FYP-II this semester even though I have cleared all prerequisite courses. I need this resolved before the registration deadline closes.',
                    ],
                    [
                        'category' => 'Other',
                        'status' => 'under_review',
                        'priority' => 'urgent',
                        'subject' => 'Request to change elective course before deadline',
                        'body' => 'I would like to switch my elective from Artificial Intelligence to Information Security before the add/drop deadline, as the AI section clashes with another core course.',
                    ],
                ],
            ],
            [
                'name' => 'Ali Hassan',
                'program_code' => 'BSSE',
                'batch' => 'S23',
                'sequence' => 103,
                'current_semester' => 5,
                'applications' => [
                    [
                        'category' => 'Result/Grade Issue',
                        'status' => 'resolved',
                        'priority' => 'urgent',
                        'subject' => 'Grade dispute for Software Engineering project',
                        'body' => 'I believe my project grade for Software Engineering was entered incorrectly. My group was awarded a B- but the rubric score sheet given to us shows an A grade.',
                        'resolution_note' => 'Reviewed the rubric with the course instructor and confirmed a data-entry error. The grade has been corrected to A on the ERP.',
                    ],
                    [
                        'category' => 'LMS/Portal Issue',
                        'status' => 'submitted',
                        'priority' => 'high',
                        'subject' => 'Unable to submit assignment due to LMS error',
                        'body' => 'The LMS shows a server error whenever I try to upload my Data Structures assignment. The deadline is tomorrow and I am worried about a late submission penalty.',
                        'messages' => [
                            ['type' => 'info_request', 'from' => 'admin', 'body' => 'Could you share a screenshot of the error message you are seeing?'],
                            ['type' => 'student_response', 'from' => 'student', 'body' => 'Attached is the screenshot showing the "500 Internal Server Error" message I get every time.'],
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Sarfraz Ahmed',
                'program_code' => 'BSDS',
                'batch' => 'F22',
                'sequence' => 105,
                'current_semester' => 7,
                'applications' => [
                    [
                        'category' => 'Other',
                        'status' => 'submitted',
                        'priority' => 'high',
                        'subject' => 'Need duplicate student ID card',
                        'body' => 'I lost my student ID card last week and need a duplicate issued as soon as possible, since I need it to enter the examination hall next week.',
                    ],
                    [
                        'category' => 'Fee Issue',
                        'status' => 'submitted',
                        'priority' => 'high',
                        'subject' => 'Scholarship discount not reflected in fee challan',
                        'body' => 'My 50% merit scholarship discount has not been applied to this semester\'s fee challan. The full amount is showing as due, which I cannot pay by the deadline.',
                    ],
                ],
            ],
            [
                'name' => 'Mustafa Raza',
                'program_code' => 'BSDS',
                'batch' => 'F23',
                'sequence' => 106,
                'current_semester' => 5,
                'applications' => [
                    [
                        'category' => 'Faculty/Teacher Related',
                        'status' => 'closed',
                        'priority' => 'urgent',
                        'subject' => 'Request to review conduct of a lab instructor',
                        'body' => 'Several students in my section have raised concerns about the lab instructor\'s conduct during the Database Systems lab. I would like to formally request a review.',
                    ],
                    [
                        'category' => 'General Query',
                        'status' => 'resolved',
                        'priority' => 'urgent',
                        'subject' => 'Query about eligibility for final year project extension',
                        'body' => 'I would like to know whether an extension is possible for the FYP-II submission given that one of my group members withdrew from the university mid-semester.',
                        'resolution_note' => 'Confirmed with the FYP coordinator that a two-week extension has been approved for your group due to the member withdrawal.',
                    ],
                    [
                        'category' => 'Academic Issue',
                        'status' => 'submitted',
                        'priority' => 'urgent',
                        'subject' => 'Clash between core course and compulsory lab timing',
                        'body' => 'My timetable has the Operating Systems lecture and the Computer Networks lab scheduled at the same time this semester, making it impossible to attend both.',
                    ],
                ],
            ],
            [
                'name' => 'Laiba Noor',
                'program_code' => 'BSDS',
                'batch' => 'S24',
                'sequence' => 107,
                'current_semester' => 2,
                'applications' => [
                    [
                        'category' => 'Other',
                        'status' => 'resolved',
                        'priority' => 'normal',
                        'subject' => 'Request for official transcript for internship application',
                        'body' => 'I need an official transcript issued urgently as I have to submit it along with my internship application by the end of this week.',
                        'resolution_note' => 'The transcript has been processed and is ready for collection from the Examination Department.',
                    ],
                ],
            ],
            [
                'name' => 'Ayesha Fatima',
                'program_code' => 'BSAI',
                'batch' => 'F23',
                'sequence' => 108,
                'current_semester' => 5,
                'applications' => [
                    [
                        'category' => 'Academic Issue',
                        'status' => 'closed',
                        'priority' => 'normal',
                        'subject' => 'Incorrect semester shown on student profile',
                        'body' => 'My student profile on the portal shows me in the 5th semester, but I am actually enrolled in the 6th semester this term.',
                    ],
                    [
                        'category' => 'Timetable Issue',
                        'status' => 'closed',
                        'priority' => 'normal',
                        'subject' => 'Overlapping timetable for two compulsory courses',
                        'body' => 'Software Quality Engineering and Human Computer Interaction have been scheduled in overlapping slots this semester, and both are compulsory for my batch.',
                    ],
                    [
                        'category' => 'Academic Issue',
                        'status' => 'in_progress',
                        'priority' => 'high',
                        'subject' => 'Request to join a different section for Software Design',
                        'body' => 'I would like to move from Section A to Section B for Software Design, as my current section\'s timing clashes with my part-time job.',
                    ],
                ],
            ],
            [
                'name' => 'Fatima Zahra',
                'program_code' => 'BSAI',
                'batch' => 'F24',
                'sequence' => 109,
                'current_semester' => 3,
                'applications' => [
                    [
                        'category' => 'General Query',
                        'status' => 'resolved',
                        'priority' => 'normal',
                        'subject' => 'Query about re-take policy for a failed course',
                        'body' => 'I failed Discrete Mathematics last semester and would like to know the process and deadline for registering it as a re-take course.',
                        'resolution_note' => 'Re-take registration opens next week; please visit the Academics Office with your transcript to complete the process.',
                    ],
                ],
            ],
            [
                'name' => 'Usman Tariq',
                'program_code' => 'BSAI',
                'batch' => 'S23',
                'sequence' => 110,
                'current_semester' => 5,
                'applications' => [
                    [
                        'category' => 'Timetable Issue',
                        'status' => 'resolved',
                        'priority' => 'normal',
                        'subject' => 'Clash between elective and compulsory lab session',
                        'body' => 'My elective course Cloud Computing has been scheduled at the same time as the compulsory Software Engineering lab, and I cannot attend both.',
                        'resolution_note' => 'The Cloud Computing elective section has been moved to a new time slot. Please check the updated timetable on the portal.',
                    ],
                ],
            ],
            [
                'name' => 'Hira Shahid',
                'program_code' => 'BSSE',
                'batch' => 'F24',
                'sequence' => 104,
                'current_semester' => 3,
                'applications' => [
                    [
                        'category' => 'Academic Issue',
                        'status' => 'closed',
                        'priority' => 'high',
                        'subject' => 'Incomplete grade shown for a completed course',
                        'body' => 'My transcript shows an incomplete (I) grade for Technical and Business Writing, even though I completed and submitted all required coursework.',
                    ],
                    [
                        'category' => 'Timetable Issue',
                        'status' => 'under_review',
                        'priority' => 'normal',
                        'subject' => 'Request to shift lab session to a different day',
                        'body' => 'I have a medical appointment every Wednesday afternoon that clashes with my Software Engineering lab. Could the lab be moved to another day for my section?',
                    ],
                    [
                        'category' => 'Registration Issue',
                        'status' => 'under_review',
                        'priority' => 'urgent',
                        'subject' => 'Unable to register for a prerequisite course',
                        'body' => 'The ERP is blocking my registration for Database Systems, saying I have not completed the prerequisite, even though I passed it last semester.',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array{program_code: string, batch: string, sequence: int}  $def
     */
    public static function registrationNo(array $def): string
    {
        return sprintf('SU92-%sM-%s-%03d', $def['program_code'], $def['batch'], $def['sequence']);
    }

    /**
     * Deterministic, always-in-the-past submission/transition timestamps
     * for the application at this position across the whole demo set
     * (0-21), so re-running either seeder produces the same dates.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function timestampsFor(int $globalIndex): array
    {
        $submittedAt = now()->subDays(30 - $globalIndex)->setTime(9, 0);
        $transitionAt = $submittedAt->copy()->addDays(3 + ($globalIndex % 5));

        return [$submittedAt, $transitionAt];
    }

    /**
     * Maps a demo application definition to Application attributes:
     * resolved_at/closed_at and created_at/updated_at follow the same
     * timestamps used for its application_events, and semester_at_submission
     * matches the student's current semester only while still active —
     * concluded applications are treated as having been submitted a
     * semester earlier.
     *
     * @param  AppDef  $appDef
     * @return array<string, mixed>
     */
    public static function applicationAttributes(array $appDef, int $globalIndex, int $currentSemester): array
    {
        $status = ApplicationStatus::from($appDef['status']);
        [$submittedAt, $transitionAt] = self::timestampsFor($globalIndex);
        $concludedAt = $status->value === 'submitted' ? $submittedAt : $transitionAt;

        $isActive = in_array($appDef['status'], self::ACTIVE_STATUSES, true);

        return [
            'subject' => $appDef['subject'],
            'body' => $appDef['body'],
            'priority' => $appDef['priority'],
            'status' => $status,
            'semester_at_submission' => $isActive ? $currentSemester : max(1, $currentSemester - 1),
            'resolution_note' => $appDef['resolution_note'] ?? null,
            'rejection_reason' => $appDef['rejection_reason'] ?? null,
            'resolved_at' => $status === ApplicationStatus::Resolved ? $concludedAt : null,
            'closed_at' => $status === ApplicationStatus::Closed ? $concludedAt : null,
            'created_at' => $submittedAt,
            'updated_at' => $concludedAt,
        ];
    }

    /**
     * Creates the application_events matching the application's own
     * status: always a "submitted" event, plus — unless it's still
     * "submitted" — one more event for whichever status it concluded at.
     *
     * @param  AppDef  $appDef
     */
    public static function createEvents(
        Application $application,
        array $appDef,
        int $globalIndex,
        int $studentUserId,
        ?int $adminUserId,
    ): void {
        [$submittedAt, $transitionAt] = self::timestampsFor($globalIndex);
        $status = ApplicationStatus::from($appDef['status']);

        // created_at isn't mass-assignable, hence forceFill rather than create().
        (new ApplicationEvent)->forceFill([
            'application_id' => $application->id,
            'user_id' => $studentUserId,
            'event_type' => ApplicationEventType::Submitted,
            'visible_to_student' => true,
            'created_at' => $submittedAt,
        ])->save();

        if ($status === ApplicationStatus::Submitted) {
            return;
        }

        $eventType = match ($status) {
            ApplicationStatus::Resolved => ApplicationEventType::Resolved,
            ApplicationStatus::Closed => ApplicationEventType::Closed,
            ApplicationStatus::Rejected => ApplicationEventType::Rejected,
            default => ApplicationEventType::StatusChanged,
        };

        (new ApplicationEvent)->forceFill([
            'application_id' => $application->id,
            'user_id' => $adminUserId,
            'event_type' => $eventType,
            'from_value' => ApplicationStatus::Submitted->value,
            'to_value' => $status->value,
            'visible_to_student' => true,
            'created_at' => $transitionAt,
        ])->save();
    }

    /**
     * Creates a short message thread for an application: free-text
     * messages, and info/document requests paired with the student
     * response that answers them (matched in the order given).
     *
     * @param  array<int, MessageDef>  $messageDefs
     */
    public static function createMessages(
        Application $application,
        array $messageDefs,
        int $globalIndex,
        int $studentUserId,
        ?int $adminUserId,
    ): void {
        [$submittedAt] = self::timestampsFor($globalIndex);
        $when = $submittedAt->copy()->addDay();
        $openRequestId = null;

        foreach ($messageDefs as $def) {
            $type = MessageType::from($def['type']);
            $senderId = $def['from'] === 'admin' ? $adminUserId : $studentUserId;

            $message = new ApplicationMessage;
            $message->forceFill([
                'application_id' => $application->id,
                'sender_id' => $senderId,
                'parent_id' => $type === MessageType::StudentResponse ? $openRequestId : null,
                'type' => $type,
                'body' => $def['body'],
                'created_at' => $when,
                'updated_at' => $when,
            ])->save();

            if (in_array($type, [MessageType::InfoRequest, MessageType::DocumentRequest], true)) {
                $openRequestId = $message->id;
            } elseif ($type === MessageType::StudentResponse && $openRequestId !== null) {
                ApplicationMessage::query()->whereKey($openRequestId)->update(['responded_at' => $when]);
                $openRequestId = null;
            }

            $when = $when->copy()->addHours(6);
        }
    }

    /**
     * A live, a scheduled and an expired announcement, so every status
     * shown on the admin list and the student announcements page has at
     * least one example. Idempotent by title, so re-running never
     * duplicates them.
     */
    public static function createAnnouncements(int $departmentId, ?int $adminUserId): void
    {
        $announcements = [
            [
                'title' => 'Mid-Semester Examination Schedule Released',
                'body' => "The mid-semester examination schedule for all programs has been published. Please check the Examination section of the portal for your exact dates and seating plan.\n\nContact the Examination Department if you notice any clash in your schedule.",
                'publish_at' => now()->subDays(2),
                'expires_at' => null,
                'is_active' => true,
            ],
            [
                'title' => 'Semester Fee Submission Deadline Reminder',
                'body' => "This is a reminder that the semester fee submission deadline is approaching. Students who do not clear their dues by the deadline may be marked as defaulters.\n\nPlease contact the Accounts Office for any payment-related queries.",
                'publish_at' => now()->addDays(5),
                'expires_at' => null,
                'is_active' => true,
            ],
            [
                'title' => 'Summer Break Office Hours',
                'body' => "During the summer break, the Software Engineering Department office will operate on reduced hours (10 AM to 2 PM, Monday to Friday).\n\nRegular hours will resume at the start of the next semester.",
                'publish_at' => now()->subDays(20),
                'expires_at' => now()->subDays(5),
                'is_active' => true,
            ],
        ];

        foreach ($announcements as $attributes) {
            Announcement::query()->firstOrCreate(
                ['department_id' => $departmentId, 'title' => $attributes['title']],
                array_merge($attributes, ['created_by' => $adminUserId]),
            );
        }
    }
}
