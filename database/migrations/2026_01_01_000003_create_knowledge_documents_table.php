<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->constrained('faq_categories')
                ->restrictOnDelete();
            $table->string('title', 255);
            $table->string('source_type', 32)->default('text')->index(); // text, file, sib_bm_sync
            $table->string('original_filename', 255)->nullable();
            $table->string('file_path', 500)->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->longText('raw_content')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('processing_status', 32)->default('pending')->index(); // pending, processing, indexed, failed
            $table->text('error_message')->nullable();
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('last_indexed_at')->nullable();
            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['category_id', 'processing_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
