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
        Schema::create('application_forwards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_user_id')->constrained('users')->restrictOnDelete();
            $table->string('from_role', 20);
            $table->string('to_role', 20);
            $table->foreignId('to_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('to_email', 255)->nullable();
            $table->text('note');
            $table->string('email_status', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['application_id', 'to_role']);
            $table->index('email_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_forwards');
    }
};
