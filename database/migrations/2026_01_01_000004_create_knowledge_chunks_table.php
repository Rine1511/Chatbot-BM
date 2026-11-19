<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')
                ->nullable()
                ->constrained('knowledge_documents')
                ->cascadeOnDelete();
            $table->foreignId('faq_id')
                ->nullable()
                ->constrained('faqs')
                ->cascadeOnDelete();
            $table->unsignedInteger('chunk_index')->default(0);
            $table->text('content');
            $table->unsignedInteger('token_count')->default(0);
            $table->char('content_hash', 64)->index();
            $table->json('metadata')->nullable();
            $table->string('vector_point_id', 64)->nullable()->unique();
            $table->timestamps();

            $table->index(['document_id', 'chunk_index']);
            $table->index(['faq_id', 'chunk_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
