<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();
            $table->string('sender_type', 20)->index(); // user | assistant
            $table->longText('content');
            $table->json('matched_sources')->nullable();
            $table->boolean('is_fallback')->default(false)->index();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('evaluation_status', 32)->default('unreviewed')->index(); // unreviewed | accurate | needs_improvement | inaccurate
            $table->text('evaluation_note')->nullable();
            $table->foreignId('evaluated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
            $table->index(['evaluation_status', 'created_at']);
            $table->index(['sender_type', 'is_fallback']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
