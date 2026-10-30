<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('code', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('available_to_hod')->default(false);
            $table->boolean('available_to_dean')->default(false);
            $table->timestamps();

            $table->unique(['department_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
