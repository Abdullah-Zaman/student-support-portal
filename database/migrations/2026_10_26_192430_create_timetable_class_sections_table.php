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
        Schema::create('timetable_class_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('timetable_class_id')->constrained()->cascadeOnDelete();
            $table->string('section', 20);
            $table->string('program_code', 10);
            $table->unsignedTinyInteger('semester');

            $table->unique(['timetable_class_id', 'section']);
            $table->index('section');
            $table->index(['program_code', 'semester']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timetable_class_sections');
    }
};
