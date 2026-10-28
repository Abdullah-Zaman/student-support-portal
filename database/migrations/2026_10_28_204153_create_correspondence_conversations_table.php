<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Private staff conversations: one per pair of parties on an
     * application (party_a is the earlier party in the fixed party order).
     */
    public function up(): void
    {
        Schema::create('correspondence_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->string('party_a', 20);
            $table->string('party_b', 20);
            $table->timestamps();

            $table->unique(['application_id', 'party_a', 'party_b'], 'correspondence_conversations_pair_unique');
        });

        Schema::table('correspondence_messages', function (Blueprint $table) {
            $table->foreignId('conversation_id')->nullable()->after('application_id')
                ->constrained('correspondence_conversations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('correspondence_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('conversation_id');
        });

        Schema::dropIfExists('correspondence_conversations');
    }
};
