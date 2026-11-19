<?php

namespace Tests\Feature;

use App\Models\ChatbotSetting;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeEmbedding;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaAndRelationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_tables_are_created_by_migrations(): void
    {
        $expectedTables = [
            'users',
            'faq_categories',
            'faqs',
            'knowledge_documents',
            'knowledge_chunks',
            'knowledge_embeddings',
            'conversations',
            'messages',
            'chatbot_settings',
            'sib_bm_buildings_cache',
        ];

        foreach ($expectedTables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Tabel {$table} tidak ditemukan.");
        }
    }

    public function test_seeders_create_six_mandatory_categories_admin_and_settings_idempotently(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $expectedCategories = [
            'Profil Pondok',
            'Pendidikan',
            'Pendaftaran',
            'Kegiatan',
            'Fasilitas',
            'Informasi Bangunan',
        ];

        $this->assertSame(6, FaqCategory::query()->count());
        foreach ($expectedCategories as $categoryName) {
            $this->assertDatabaseHas('faq_categories', ['name' => $categoryName]);
        }

        $this->assertDatabaseHas('users', [
            'email' => env('ADMIN_SEED_EMAIL', 'admin@bahrulmaghfirohmalang.or.id'),
            'role' => User::ROLE_SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->assertSame(
            'Asisten Virtual Bahrul Maghfiroh',
            ChatbotSetting::getValue('chatbot_name')
        );
    }

    public function test_shared_category_relations_metadata_and_restrict_delete_work_correctly(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->firstOrFail();
        $category = FaqCategory::query()->where('slug', 'profil-pondok')->firstOrFail();

        $faq = Faq::query()->create([
            'category_id' => $category->id,
            'question' => 'Contoh pertanyaan uji relasi kategori?',
            'answer' => 'Contoh jawaban uji relasi.',
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $document = KnowledgeDocument::query()->create([
            'category_id' => $category->id,
            'title' => 'Dokumen Uji Relasi',
            'source_type' => 'text',
            'raw_content' => 'Konten teks pengujian relasi dokumen.',
            'processing_status' => 'indexed',
            'chunk_count' => 1,
            'uploaded_by' => $admin->id,
        ]);

        $chunk = KnowledgeChunk::query()->create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Konten teks pengujian relasi dokumen.',
            'token_count' => 6,
            'content_hash' => hash('sha256', 'Konten teks pengujian relasi dokumen.'),
            'metadata' => [
                'document_id' => $document->id,
                'faq_id' => null,
                'category_id' => $category->id,
                'category_slug' => $category->slug,
                'source_type' => 'text',
            ],
            'vector_point_id' => 'test-point-001',
        ]);

        $embedding = KnowledgeEmbedding::query()->create([
            'chunk_id' => $chunk->id,
            'embedding_model' => 'gemini-embedding-001',
            'dimensions' => 768,
            'vector_data' => [0.1, 0.2, 0.3],
        ]);

        $this->assertTrue($faq->category->is($category));
        $this->assertTrue($document->category->is($category));
        $this->assertCount(1, $category->faqs);
        $this->assertCount(1, $category->knowledgeDocuments);
        $this->assertTrue($chunk->document->is($document));
        $this->assertTrue($chunk->embedding->is($embedding));
        $this->assertSame($category->id, $chunk->metadata['category_id']);
        $this->assertSame($document->id, $chunk->metadata['document_id']);

        // Menghapus kategori yang masih digunakan harus ditolak (restrictOnDelete)
        $this->expectException(QueryException::class);
        $category->delete();
    }

    public function test_deleting_document_cascades_to_chunks_and_embeddings_and_conversation_relations(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->firstOrFail();
        $category = FaqCategory::query()->where('slug', 'pendidikan')->firstOrFail();

        $document = KnowledgeDocument::query()->create([
            'category_id' => $category->id,
            'title' => 'Dokumen Uji Cascade',
            'source_type' => 'text',
            'raw_content' => 'Konten cascade.',
            'uploaded_by' => $admin->id,
        ]);

        $chunk = KnowledgeChunk::query()->create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Konten cascade.',
            'token_count' => 2,
            'content_hash' => hash('sha256', 'Konten cascade.'),
        ]);

        KnowledgeEmbedding::query()->create([
            'chunk_id' => $chunk->id,
            'embedding_model' => 'gemini-embedding-001',
            'dimensions' => 768,
            'vector_data' => [0.5, 0.5],
        ]);

        $document->delete();

        $this->assertDatabaseCount('knowledge_chunks', 0);
        $this->assertDatabaseCount('knowledge_embeddings', 0);

        $conversation = Conversation::query()->create([
            'session_token' => hash('sha256', 'session-1'),
            'origin_domain' => 'https://bahrulmaghfirohmalang.or.id',
            'started_at' => now(),
            'last_message_at' => now(),
            'message_count' => 1,
        ]);

        $message = Message::query()->create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'assistant',
            'content' => 'Jawaban pengujian.',
            'evaluated_by' => $admin->id,
            'evaluation_status' => 'accurate',
        ]);

        $this->assertTrue($message->conversation->is($conversation));
        $this->assertTrue($message->evaluator->is($admin));
    }

    public function test_vector_point_id_cannot_be_duplicated_across_chunks(): void
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->firstOrFail();
        $category = FaqCategory::query()->where('slug', 'fasilitas')->firstOrFail();

        $document = KnowledgeDocument::query()->create([
            'category_id' => $category->id,
            'title' => 'Dokumen Uji Unique Point',
            'source_type' => 'text',
            'raw_content' => 'Konten 1 dan Konten 2.',
            'uploaded_by' => $admin->id,
        ]);

        KnowledgeChunk::query()->create([
            'document_id' => $document->id,
            'chunk_index' => 0,
            'content' => 'Konten 1.',
            'token_count' => 2,
            'content_hash' => hash('sha256', 'Konten 1.'),
            'vector_point_id' => 'dup-point-uuid',
        ]);

        $this->expectException(QueryException::class);

        KnowledgeChunk::query()->create([
            'document_id' => $document->id,
            'chunk_index' => 1,
            'content' => 'Konten 2.',
            'token_count' => 2,
            'content_hash' => hash('sha256', 'Konten 2.'),
            'vector_point_id' => 'dup-point-uuid',
        ]);
    }
}
