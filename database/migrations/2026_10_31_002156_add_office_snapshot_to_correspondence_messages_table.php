<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A forward to an office contact keeps a copy of who the recipient
     * was, so editing the contact later never rewrites history.
     */
    public function up(): void
    {
        Schema::table('correspondence_messages', function (Blueprint $table) {
            $table->foreignId('office_contact_id')->nullable()->after('to_email')->constrained()->nullOnDelete();
            $table->string('to_name', 100)->nullable()->after('office_contact_id');
            $table->string('to_designation', 100)->nullable()->after('to_name');
            $table->string('to_office_name', 100)->nullable()->after('to_designation');
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_contact_id');
            $table->dropColumn(['to_name', 'to_designation', 'to_office_name']);
        });
    }
};
