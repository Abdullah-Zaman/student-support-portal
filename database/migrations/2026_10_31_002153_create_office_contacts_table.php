<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->string('designation', 100)->nullable();
            $table->string('email', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['office_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_contacts');
    }
};
