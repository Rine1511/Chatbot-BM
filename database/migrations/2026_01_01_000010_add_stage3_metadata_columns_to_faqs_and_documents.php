<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->string('keywords', 500)->nullable()->after('answer');
            $table->text('question_variations')->nullable()->after('keywords');
        });

        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
            $table->string('source_label', 255)->nullable()->after('raw_content');
            $table->boolean('is_active')->default(true)->after('processing_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn(['keywords', 'question_variations']);
        });

        Schema::table('knowledge_documents', function (Blueprint $table) {
            $table->dropIndex(['is_active']);
            $table->dropColumn(['description', 'source_label', 'is_active']);
        });
    }
};
