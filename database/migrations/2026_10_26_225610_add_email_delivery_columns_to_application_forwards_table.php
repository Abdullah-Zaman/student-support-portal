<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('application_forwards', function (Blueprint $table) {
            $table->unsignedTinyInteger('email_attempts')->default(0)->after('email_status');
            $table->string('email_error', 500)->nullable()->after('email_attempts');
            $table->timestamp('emailed_at')->nullable()->after('email_error');
            $table->string('gmail_message_id', 100)->nullable()->after('emailed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('application_forwards', function (Blueprint $table) {
            $table->dropColumn(['email_attempts', 'email_error', 'emailed_at', 'gmail_message_id']);
        });
    }
};
