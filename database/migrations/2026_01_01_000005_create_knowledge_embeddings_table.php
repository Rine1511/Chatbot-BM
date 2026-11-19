<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chunk_id')
                ->unique()
                ->constrained('knowledge_chunks')
                ->cascadeOnDelete();
            $table->string('embedding_model', 120)->index();
            $table->unsignedInteger('dimensions');
            $table->longText('vector_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_embeddings');
    }
};
