<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->boolean('requires_acknowledgement')->default(false)->after('is_active');
            $table->string('survey_url', 500)->nullable()->after('requires_acknowledgement');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn(['requires_acknowledgement', 'survey_url']);
        });
    }
};
