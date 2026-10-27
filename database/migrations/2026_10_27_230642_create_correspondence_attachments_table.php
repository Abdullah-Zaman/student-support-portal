<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files on correspondence messages. Kept apart from
     * application_attachments so they can never reach a student.
     */
    public function up(): void
    {
        Schema::create('correspondence_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('correspondence_message_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_name', 255);
            $table->string('file_path', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();

            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('correspondence_attachments');
    }
};
