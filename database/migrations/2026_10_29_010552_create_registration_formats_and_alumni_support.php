<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additional student registration formats (set by the Super Admin as
     * simple templates, never raw regex) and support for alumni, who have
     * no current semester.
     */
    public function up(): void
    {
        Schema::create('registration_formats', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('email_template', 100);
            $table->string('registration_no_template', 100);
            $table->string('program_code', 20)->nullable();
            $table->string('batch', 3)->nullable();
            $table->boolean('is_alumni')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_semester')->nullable()->change();
            $table->boolean('is_alumni')->default(false)->after('batch');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester_at_submission')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedTinyInteger('semester_at_submission')->nullable(false)->change();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn('is_alumni');
            $table->unsignedTinyInteger('current_semester')->nullable(false)->change();
        });

        Schema::dropIfExists('registration_formats');
    }
};
