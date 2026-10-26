<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every existing department is attached to the Faculty of Computing
     * before faculty_id becomes required, so no row is left without one.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('faculty_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->after('code');
        });

        if (DB::table('departments')->exists()) {
            $facultyId = DB::table('faculties')->where('code', 'FOC')->value('id')
                ?? DB::table('faculties')->insertGetId([
                    'name' => 'Faculty of Computing',
                    'code' => 'FOC',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('departments')->whereNull('faculty_id')->update(['faculty_id' => $facultyId]);
        }

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignId('faculty_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faculty_id');
            $table->dropColumn('is_active');
        });
    }
};
