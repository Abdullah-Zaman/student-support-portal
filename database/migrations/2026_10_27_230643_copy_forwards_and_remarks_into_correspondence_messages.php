<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Copy every forward and remark into the correspondence thread, in
     * the order they happened. The old tables are left untouched (they
     * are dropped in a later migration once the copy is verified). Copied
     * rows are marked as seen so nobody gets a flood of "unread" items,
     * and a forward whose email never went out becomes "failed" so its
     * sender can retry it. Running it twice copies nothing new.
     */
    public function up(): void
    {
        $forwards = DB::table('application_forwards')->get();

        $forwardRows = $forwards->map(fn (object $forward) => [
            'sort_at' => (string) $forward->created_at,
            'sort_id' => $forward->id * 2,
            'row' => [
                'application_id' => $forward->application_id,
                'type' => 'forward',
                'author_id' => $forward->from_user_id,
                'from_party' => $forward->from_role === 'super_admin' ? 'admin_officer' : $forward->from_role,
                'from_user_id' => $forward->from_user_id,
                'to_party' => $forward->to_role,
                'to_user_id' => $forward->to_user_id,
                'to_email' => $forward->to_email,
                'body' => $forward->note,
                'email_status' => match ($forward->email_status) {
                    'sent' => 'sent',
                    'not_required' => 'not_emailed',
                    default => 'failed',
                },
                'email_attempts' => $forward->email_attempts,
                'email_error' => match ($forward->email_status) {
                    'sent', 'not_required' => null,
                    'failed' => $forward->email_error,
                    default => 'Not sent before the correspondence upgrade. Retry to send it.',
                },
                'emailed_at' => $forward->emailed_at,
                'gmail_message_id' => $forward->gmail_message_id,
                'idempotency_key' => 'legacy-forward-'.$forward->id,
                'seen_at' => $forward->created_at,
                'created_at' => $forward->created_at,
            ],
        ]);

        $forwardSenders = $forwards->pluck('from_user_id', 'id');

        $remarkRows = DB::table('application_remarks')->get()->map(fn (object $remark) => [
            'sort_at' => (string) $remark->created_at,
            'sort_id' => $remark->id * 2 + 1,
            'row' => [
                'application_id' => $remark->application_id,
                'type' => 'remark',
                'author_id' => $remark->author_id,
                'from_party' => $remark->author_role,
                'from_user_id' => $remark->author_id,
                'to_party' => 'admin_officer',
                'to_user_id' => $this->adminOfficerFor($remark->application_id, $forwardSenders[$remark->application_forward_id] ?? null),
                'remark_type' => $remark->type,
                'body' => $remark->body,
                'email_status' => 'not_emailed',
                'idempotency_key' => 'legacy-remark-'.$remark->id,
                'seen_at' => $remark->created_at,
                'created_at' => $remark->created_at,
            ],
        ]);

        $copiedKeys = DB::table('correspondence_messages')
            ->where('idempotency_key', 'like', 'legacy-%')
            ->pluck('idempotency_key')
            ->flip();

        $forwardRows->concat($remarkRows)
            ->sortBy([['sort_at', 'asc'], ['sort_id', 'asc']])
            ->reject(fn (array $item) => $copiedKeys->has($item['row']['idempotency_key']))
            ->each(fn (array $item) => DB::table('correspondence_messages')->insert($item['row']));
    }

    /**
     * Remove only the copied rows; anything written since stays.
     */
    public function down(): void
    {
        DB::table('correspondence_messages')->where('idempotency_key', 'like', 'legacy-%')->delete();
    }

    /**
     * Remarks went to the department's Admin Officers; record the first
     * one (or, failing that, whoever forwarded to the reviewer).
     */
    private function adminOfficerFor(int $applicationId, ?int $fallbackUserId): ?int
    {
        $departmentId = DB::table('applications')->where('id', $applicationId)->value('department_id');

        $adminOfficerId = DB::table('users')
            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', 'App\Models\User')
            ->where('roles.name', 'admin_officer')
            ->where('users.department_id', $departmentId)
            ->orderBy('users.id')
            ->value('users.id');

        return $adminOfficerId !== null ? (int) $adminOfficerId : $fallbackUserId;
    }
};
