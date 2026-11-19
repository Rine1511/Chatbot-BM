<?php

namespace Tests\Feature;

use App\Jobs\IndexKnowledgeDocumentJob;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeIndexingPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_index_and_reindex_document_using_mysql_vector_driver(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_GEMINI_API_KEY_123');
        config()->set('ai.rag.chunk_size', 160);
        config()->set('ai.rag.chunk_overlap', 30);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.125)],
            ], 200),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $category = FaqCategory::query()->where('slug', 'pendidikan')->firstOrFail();

        $document = KnowledgeDocument::query()->create([
            'category_id' => $category->id,
            'title' => 'Kurikulum Pendidikan Pondok Pesantren',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'source_label' => 'Buku Pedoman Akademik',
            'raw_content' => str_repeat('Santri mengikuti pembelajaran kitab kuning, tahfidz Al-Quran, dan pendidikan formal sesuai jadwal resmi pondok. ', 4),
            'processing_status' => KnowledgeDocument::STATUS_PENDING,
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.knowledge.index-document', $document));
        $response->assertRedirect(route('admin.knowledge.show', $document));
        $response->assertSessionHas('status');

        $document->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_INDEXED, $document->processing_status);
        $this->assertNull($document->error_message);
        $this->assertGreaterThan(1, $document->chunk_count);
        $this->assertNotNull($document->last_indexed_at);

        $firstChunkIds = $document->chunks()->pluck('id')->all();
        $this->assertCount($document->chunk_count, $firstChunkIds);
        $this->assertDatabaseCount('knowledge_embeddings', $document->chunk_count);

        foreach ($document->chunks()->with('embedding')->get() as $chunk) {
            $this->assertNotNull($chunk->vector_point_id);
            $this->assertSame(768, $chunk->embedding->dimensions);
            $this->assertSame('gemini-embedding-001', $chunk->embedding->embedding_model);
            $this->assertSame('mysql', $chunk->metadata['vector_driver']);
            $this->assertSame('pendidikan', $chunk->metadata['category_slug']);
        }

        // Verify detail view displays chunk details
        $this->actingAs($admin)
            ->get(route('admin.knowledge.show', $document))
            ->assertOk()
            ->assertSee('Chunk #1')
            ->assertSee('gemini-embedding-001 (768d)')
            ->assertSee('Re-index Dokumen');

        // Re-index replaces old chunks cleanly without duplicates
        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $document))
            ->assertRedirect(route('admin.knowledge.show', $document));

        $document->refresh();
        $secondChunkIds = $document->chunks()->pluck('id')->all();
        $this->assertCount($document->chunk_count, $secondChunkIds);
        $this->assertEmpty(array_intersect($firstChunkIds, $secondChunkIds));
        $this->assertDatabaseCount('knowledge_chunks', $document->chunk_count);
        $this->assertDatabaseCount('knowledge_embeddings', $document->chunk_count);
    }

    public function test_pipeline_falls_back_to_mysql_when_qdrant_is_unavailable(): void
    {
        config()->set('ai.vector.driver', 'qdrant');
        config()->set('ai.vector.fallback_driver', 'mysql');
        config()->set('ai.vector.qdrant.host', 'http://127.0.0.1:19999');
        config()->set('ai.providers.gemini.api_key', 'TEST_GEMINI_API_KEY_123');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.5)],
            ], 200),
            '127.0.0.1:19999/*' => Http::response([], 503),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $category = FaqCategory::query()->where('slug', 'fasilitas')->firstOrFail();

        $document = KnowledgeDocument::query()->create([
            'category_id' => $category->id,
            'title' => 'Fasilitas Masjid & Asrama',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Masjid utama pondok digunakan untuk sholat berjamaah dan pengajian kitab rutin santri.',
            'processing_status' => KnowledgeDocument::STATUS_PENDING,
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $document))
            ->assertRedirect(route('admin.knowledge.show', $document));

        $document->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_INDEXED, $document->processing_status);
        $this->assertSame('mysql', $document->chunks()->firstOrFail()->metadata['vector_driver']);
    }

    public function test_qdrant_reindexing_and_document_deletion_clean_up_old_points(): void
    {
        Storage::fake('local');
        config()->set('ai.vector.driver', 'qdrant');
        config()->set('ai.vector.fallback_driver', '');
        config()->set('ai.vector.qdrant.host', 'http://127.0.0.1:6333');
        config()->set('ai.providers.gemini.api_key', 'TEST_GEMINI_API_KEY_123');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.3)],
            ], 200),
            'http://127.0.0.1:6333/collections' => Http::response(['result' => ['collections' => []]], 200),
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks' => Http::response([
                'result' => ['config' => ['params' => ['vectors' => ['size' => 768, 'distance' => 'Cosine']]]],
            ], 200),
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks/points*' => Http::response([
                'status' => 'ok',
                'result' => ['status' => 'completed'],
            ], 200),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $category = FaqCategory::query()->where('slug', 'informasi-bangunan')->firstOrFail();

        $file = UploadedFile::fake()->createWithContent(
            'gedung-sibbm.txt',
            'Gedung Pusat Informasi dan Administrasi berada di sisi utara kompleks pondok pesantren.'
        );

        $this->actingAs($admin)->post(route('admin.knowledge.store'), [
            'category_id' => $category->id,
            'title' => 'Data Gedung SIB-BM',
            'source_type' => KnowledgeDocument::SOURCE_FILE,
            'document_file' => $file,
            'is_active' => '1',
        ]);

        $document = KnowledgeDocument::query()->firstOrFail();

        // First index
        $this->actingAs($admin)->post(route('admin.knowledge.index-document', $document));
        $document->refresh();
        $firstPointId = $document->chunks()->firstOrFail()->vector_point_id;

        // Re-index -> should delete $firstPointId from Qdrant
        $this->actingAs($admin)->post(route('admin.knowledge.index-document', $document));
        $document->refresh();
        $secondPointId = $document->chunks()->firstOrFail()->vector_point_id;
        $this->assertNotSame($firstPointId, $secondPointId);

        // Delete document -> should delete $secondPointId from Qdrant and clean up DB + file
        $filePath = $document->file_path;
        $this->actingAs($admin)
            ->delete(route('admin.knowledge.destroy', $document))
            ->assertRedirect(route('admin.knowledge.index'));

        $this->assertDatabaseMissing('knowledge_documents', ['id' => $document->id]);
        $this->assertDatabaseCount('knowledge_chunks', 0);
        $this->assertDatabaseCount('knowledge_embeddings', 0);
        Storage::disk('local')->assertMissing($filePath);

        Http::assertSent(function (Request $request) use ($firstPointId): bool {
            return str_contains($request->url(), '/points/delete')
                && in_array($firstPointId, $request['points'] ?? [], true);
        });
        Http::assertSent(function (Request $request) use ($secondPointId): bool {
            return str_contains($request->url(), '/points/delete')
                && in_array($secondPointId, $request['points'] ?? [], true);
        });
    }

    public function test_failed_embedding_marks_document_failed_without_leaking_key_and_preserves_old_chunks(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'SECRET_KEY_MUST_NOT_LEAK_777');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['embedding' => ['values' => array_fill(0, 768, 0.2)]], 200)
                ->push(['error' => ['message' => 'Rate limit reached for key SECRET_KEY_MUST_NOT_LEAK_777']], 429)
                ->push(['error' => ['message' => 'Rate limit reached']], 429)
                ->push(['error' => ['message' => 'Rate limit reached']], 429),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $category = FaqCategory::query()->where('slug', 'pendaftaran')->firstOrFail();

        $document = KnowledgeDocument::query()->create([
            'category_id' => $category->id,
            'title' => 'Panduan Pendaftaran Resmi',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Prosedur pendaftaran santri baru dilaksanakan melalui laman resmi dan sekretariat.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        // Initial index succeeds
        $this->actingAs($admin)->post(route('admin.knowledge.index-document', $document));
        $document->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_INDEXED, $document->processing_status);
        $oldChunkId = $document->chunks()->firstOrFail()->id;

        // Re-index fails on 429 -> marks failed, hides key, and keeps existing valid chunk intact
        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $document))
            ->assertRedirect(route('admin.knowledge.show', $document))
            ->assertSessionHasErrors('indexing');

        $document->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_FAILED, $document->processing_status);
        $this->assertNotNull($document->error_message);
        $this->assertStringNotContainsString('SECRET_KEY_MUST_NOT_LEAK_777', $document->error_message);
        $this->assertDatabaseHas('knowledge_chunks', ['id' => $oldChunkId]);
    }

    public function test_inactive_document_or_inactive_category_is_excluded_from_indexing_and_batch_processing(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_GEMINI_API_KEY_123');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => array_fill(0, 768, 0.4)],
            ], 200),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $activeCat = FaqCategory::query()->where('slug', 'kegiatan')->firstOrFail();
        $inactiveCat = FaqCategory::query()->where('slug', 'profil-pondok')->firstOrFail();
        $inactiveCat->update(['is_active' => false]);

        $inactiveDoc = KnowledgeDocument::query()->create([
            'category_id' => $activeCat->id,
            'title' => 'Dokumen Nonaktif',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Materi nonaktif tidak boleh diproses.',
            'is_active' => false,
            'uploaded_by' => $admin->id,
        ]);

        $docInInactiveCat = KnowledgeDocument::query()->create([
            'category_id' => $inactiveCat->id,
            'title' => 'Dokumen Kategori Nonaktif',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Materi dalam kategori nonaktif tidak boleh diproses.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $eligibleDoc = KnowledgeDocument::query()->create([
            'category_id' => $activeCat->id,
            'title' => 'Jadwal Kegiatan Harian Santri',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Kegiatan santri meliputi sholat berjamaah, kajian kitab, dan belajar mandiri.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $inactiveDoc))
            ->assertSessionHasErrors('indexing');

        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $docInInactiveCat))
            ->assertSessionHasErrors('indexing');

        // Batch process-pending only processes the eligible active document
        $this->actingAs($admin)
            ->post(route('admin.knowledge.process-pending'))
            ->assertRedirect(route('admin.knowledge.index'))
            ->assertSessionHas('status');

        $this->assertSame(KnowledgeDocument::STATUS_PENDING, $inactiveDoc->fresh()->processing_status);
        $this->assertSame(KnowledgeDocument::STATUS_PENDING, $docInInactiveCat->fresh()->processing_status);
        $this->assertSame(KnowledgeDocument::STATUS_INDEXED, $eligibleDoc->fresh()->processing_status);
    }

    public function test_async_queue_dispatch_prevents_double_processing(): void
    {
        Queue::fake();
        config()->set('queue.default', 'database');

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->firstOrFail();

        $document = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Dokumen Antrean Queue',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Konten dokumen untuk pengujian antrean queue Laravel.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $document), ['async' => '1'])
            ->assertRedirect(route('admin.knowledge.show', $document))
            ->assertSessionHas('status');

        Queue::assertPushed(IndexKnowledgeDocumentJob::class, 1);
        $this->assertSame(KnowledgeDocument::STATUS_PROCESSING, $document->fresh()->processing_status);

        // Second dispatch while status is processing must be rejected cleanly
        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $document), ['async' => '1'])
            ->assertSessionHasErrors('indexing');

        Queue::assertPushed(IndexKnowledgeDocumentJob::class, 1);
    }
}
