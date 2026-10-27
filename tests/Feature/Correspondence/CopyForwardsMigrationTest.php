<?php

use App\Enums\CorrespondenceEmailStatus;
use App\Enums\CorrespondenceParty;
use App\Enums\CorrespondenceType;
use App\Enums\RemarkType;
use App\Models\CorrespondenceMessage;
use App\Models\Department;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    createRoles();
});

function copyForwardsMigration(): Migration
{
    return require database_path('migrations/2026_10_27_230643_copy_forwards_and_remarks_into_correspondence_messages.php');
}

test('existing forwards and remarks are copied into the thread in order, once, and can be rolled back', function () {
    $department = Department::factory()->create();
    $admin = createAdminOfficer($department);
    $hod = createHod($department);
    $application = createApplicationInDepartment($department, createStudentUser($department));

    $sentForwardId = DB::table('application_forwards')->insertGetId([
        'application_id' => $application->id,
        'from_user_id' => $admin->id,
        'from_role' => 'admin_officer',
        'to_role' => 'hod',
        'to_user_id' => $hod->id,
        'note' => 'Please advise.',
        'email_status' => 'sent',
        'gmail_message_id' => 'gmail-old-1',
        'created_at' => '2026-10-20 09:00:00',
    ]);
    DB::table('application_remarks')->insert([
        'application_id' => $application->id,
        'application_forward_id' => $sentForwardId,
        'author_id' => $hod->id,
        'author_role' => 'hod',
        'type' => 'recommended',
        'body' => 'Supported.',
        'created_at' => '2026-10-20 12:00:00',
    ]);
    DB::table('application_forwards')->insert([
        'application_id' => $application->id,
        'from_user_id' => $hod->id,
        'from_role' => 'hod',
        'to_role' => 'registrar',
        'to_email' => 'registrar@superior.edu.pk',
        'note' => 'Please process.',
        'email_status' => 'pending_email',
        'created_at' => '2026-10-21 09:00:00',
    ]);

    copyForwardsMigration()->up();
    copyForwardsMigration()->up();

    $messages = CorrespondenceMessage::query()->orderBy('id')->get();
    expect($messages)->toHaveCount(3)
        ->and($messages->pluck('type')->all())->toBe([CorrespondenceType::Forward, CorrespondenceType::Remark, CorrespondenceType::Forward]);

    [$forward, $remark, $registrarForward] = $messages;
    expect($forward->to_user_id)->toBe($hod->id)
        ->and($forward->email_status)->toBe(CorrespondenceEmailStatus::Sent)
        ->and($forward->body)->toBe('Please advise.')
        ->and($forward->seen_at)->not->toBeNull()
        ->and($remark->from_party)->toBe(CorrespondenceParty::Hod)
        ->and($remark->to_party)->toBe(CorrespondenceParty::AdminOfficer)
        ->and($remark->to_user_id)->toBe($admin->id)
        ->and($remark->remark_type)->toBe(RemarkType::Recommended)
        ->and($registrarForward->to_email)->toBe('registrar@superior.edu.pk')
        ->and($registrarForward->email_status)->toBe(CorrespondenceEmailStatus::Failed)
        ->and($registrarForward->email_error)->toContain('Retry to send it');

    copyForwardsMigration()->down();

    expect(CorrespondenceMessage::query()->count())->toBe(0)
        ->and(DB::table('application_forwards')->count())->toBe(2)
        ->and(DB::table('application_remarks')->count())->toBe(1);
});
