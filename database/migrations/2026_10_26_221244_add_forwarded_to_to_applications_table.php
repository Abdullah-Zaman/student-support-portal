<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the application was last forwarded (hod, dean or registrar),
     * for the "With HOD" badge and the "Forwarded" list filter.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->string('forwarded_to', 20)->nullable()->after('status')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['forwarded_to']);
            $table->dropColumn('forwarded_to');
        });
    }
};
