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
        Schema::create('google_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('google_email', 255);
            $table->text('access_token')->nullable();
            $table->text('refresh_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->text('scopes')->nullable();
            $table->boolean('needs_reconnect')->default(false);
            $table->timestamp('connected_at');
            $table->timestamps();

            $table->index('google_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_accounts');
    }
};
