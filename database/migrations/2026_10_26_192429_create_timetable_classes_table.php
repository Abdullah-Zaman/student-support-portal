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
        Schema::create('timetable_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('room', 100);
            $table->string('floor', 50)->nullable();
            $table->string('course', 200);
            $table->string('type', 20);
            $table->string('teacher', 150)->nullable();
            $table->string('raw_sections', 255);
            $table->string('sheet', 100);
            $table->string('cell', 20);
            $table->timestamps();

            $table->index(['timetable_version_id', 'day']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_classes');
    }
};
