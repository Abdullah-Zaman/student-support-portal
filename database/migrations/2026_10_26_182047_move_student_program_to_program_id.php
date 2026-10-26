<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The programs that were hardcoded in config before this migration.
     *
     * @var array<string, string>
     */
    private const LEGACY_PROGRAMS = [
        'BSSE' => 'BS Software Engineering',
        'BSDS' => 'BS Data Science',
        'BSAI' => 'BS Artificial Intelligence',
    ];

    /**
     * Replaces students.program (a stored label such as "BS Software
     * Engineering") with a program_id foreign key. Every student is
     * matched to a program before any column is touched; if even one
     * can't be matched, the migration stops and the schema is unchanged.
     */
    public function up(): void
    {
        $students = DB::table('students')->get(['id', 'registration_no', 'program']);

        if ($students->isNotEmpty()) {
            $this->ensureLegacyProgramsExist();
        }

        /** @var array<string, int> $programIdsByCode */
        $programIdsByCode = DB::table('programs')->pluck('id', 'code')->all();

        /** @var array<string, int> $programIdsByName */
        $programIdsByName = DB::table('programs')->pluck('id', 'name')->all();

        $programIdByStudentId = [];
        $unmatched = [];

        foreach ($students as $student) {
            $programId = $programIdsByName[$student->program]
                ?? $programIdsByCode[strtoupper((string) $student->program)]
                ?? $this->programIdFromRegistrationNo((string) $student->registration_no, $programIdsByCode);

            if ($programId === null) {
                $unmatched[] = $student->registration_no;

                continue;
            }

            $programIdByStudentId[$student->id] = $programId;
        }

        if ($unmatched !== []) {
            throw new RuntimeException('Could not match these students to a program: '.implode(', ', $unmatched));
        }

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('registration_no')->constrained()->restrictOnDelete();
            $table->index('batch');
        });

        foreach ($programIdByStudentId as $studentId => $programId) {
            DB::table('students')->where('id', $studentId)->update(['program_id' => $programId]);
        }

        Schema::table('students', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable(false)->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('program');
        });
    }

    /**
     * Restores the label column from each student's program name.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('program', 100)->nullable()->after('registration_no');
        });

        foreach (DB::table('programs')->pluck('name', 'id') as $programId => $name) {
            DB::table('students')->where('program_id', $programId)->update(['program' => $name]);
        }

        Schema::table('students', function (Blueprint $table) {
            $table->string('program', 100)->nullable(false)->change();
            $table->dropIndex(['batch']);
            $table->dropConstrainedForeignId('program_id');
        });
    }

    private function ensureLegacyProgramsExist(): void
    {
        $departmentId = DB::table('departments')->where('code', 'SE')->value('id');

        if ($departmentId === null) {
            return;
        }

        foreach (self::LEGACY_PROGRAMS as $code => $name) {
            if (DB::table('programs')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('programs')->insert([
                'department_id' => $departmentId,
                'code' => $code,
                'name' => $name,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $programIdsByCode
     */
    private function programIdFromRegistrationNo(string $registrationNo, array $programIdsByCode): ?int
    {
        if (! preg_match('/^SU\d{2}-([A-Z]+)[ME]-/', strtoupper($registrationNo), $matches)) {
            return null;
        }

        return $programIdsByCode[$matches[1]] ?? null;
    }
};
