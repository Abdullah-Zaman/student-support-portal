<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One staff correspondence thread per application: forwards, replies,
     * HOD/Dean remarks and logged Registrar replies. Staff-only.
     */
    public function up(): void
    {
        Schema::create('correspondence_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('from_party', 20);
            $table->foreignId('from_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('from_email', 255)->nullable();
            $table->string('to_party', 20);
            $table->foreignId('to_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('to_email', 255)->nullable();
            $table->string('remark_type', 20)->nullable();
            $table->text('body')->nullable();
            $table->string('email_status', 20);
            $table->unsignedTinyInteger('email_attempts')->default(0);
            $table->string('email_error', 500)->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('rfc_message_id', 255)->nullable()->unique();
            $table->string('gmail_message_id', 100)->nullable();
            $table->string('gmail_thread_id', 100)->nullable();
            $table->string('idempotency_key', 64);
            $table->timestamp('seen_at')->nullable();
            $table->foreignId('seen_by_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['author_id', 'idempotency_key']);
            $table->index(['application_id', 'to_party', 'seen_at']);
            $table->index(['application_id', 'from_party']);
            $table->index('email_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correspondence_messages');
    }
};
