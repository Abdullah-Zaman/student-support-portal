<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional address the portal sends a staff member's correspondence
     * to instead of their login email (empty = the login email).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('mail_to_email', 255)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('mail_to_email');
        });
    }
};
