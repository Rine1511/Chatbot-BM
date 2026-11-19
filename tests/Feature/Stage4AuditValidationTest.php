<?php

namespace Tests\Feature;

use App\Exceptions\VectorStoreException;
use App\Jobs\IndexKnowledgeDocumentJob;
use App\Models\FaqCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\Knowledge\KnowledgeIndexingService;
use App\Services\VectorStore\MysqlVectorStore;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class Stage4AuditValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_end_to_end_create_and_index_stores_768d_chunks_and_embeddings(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_AUDIT_KEY_123');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => $this->normalizedVector(768, [0 => 1.0])],
            ], 200),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $category = FaqCategory::query()->where('slug', 'profil-pondok')->firstOrFail();

        $storeRes = $this->actingAs($admin)->post(route('admin.knowledge.store'), [
            'category_id' => $category->id,
            'title' => 'Sejarah Pondok Pesantren Bahrul Maghfiroh',
            'description' => 'Dokumen profil resmi pondok.',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'source_label' => 'Website Resmi',
            'raw_content' => 'Pondok Pesantren Bahrul Maghfiroh berada di Kota Malang Jawa Timur dan membina santri secara terpadu.',
            'is_active' => '1',
        ]);

        $document = KnowledgeDocument::query()->firstOrFail();
        $storeRes->assertRedirect(route('admin.knowledge.show', $document));

        $indexRes = $this->actingAs($admin)->post(route('admin.knowledge.index-document', $document));
        $indexRes->assertRedirect(route('admin.knowledge.show', $document));

        $document->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_INDEXED, $document->processing_status);
        $this->assertGreaterThanOrEqual(1, $document->chunk_count);
        $this->assertNotNull($document->last_indexed_at);

        $chunk = $document->chunks()->with('embedding')->firstOrFail();
        $this->assertSame(768, $chunk->embedding->dimensions);
        $this->assertCount(768, $chunk->embedding->vector_data);
        $this->assertNotNull($chunk->metadata['document_content_hash'] ?? null);
    }

    public function test_mysql_vector_search_orders_results_by_cosine_similarity_descending(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->where('slug', 'pendidikan')->firstOrFail();

        $doc = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Uji Urutan Cosine Similarity',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Konten uji.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $store = new MysqlVectorStore;
        $queryVec = $this->normalizedVector(768, [0 => 1.0]);
        $vecExact = $this->normalizedVector(768, [0 => 1.0]);           // cos = 1.0
        $vecPartial = $this->normalizedVector(768, [0 => 1.0, 1 => 1.0]); // cos ~ 0.707107
        $vecOrtho = $this->normalizedVector(768, [2 => 1.0]);           // cos = 0.0

        $vectors = [
            ['idx' => 0, 'label' => 'ortho', 'vec' => $vecOrtho],
            ['idx' => 1, 'label' => 'partial', 'vec' => $vecPartial],
            ['idx' => 2, 'label' => 'exact', 'vec' => $vecExact],
        ];

        $pointByLabel = [];
        foreach ($vectors as $item) {
            $chunk = KnowledgeChunk::query()->create([
                'document_id' => $doc->id,
                'chunk_index' => $item['idx'],
                'content' => 'Chunk '.$item['label'],
                'token_count' => 3,
                'content_hash' => hash('sha256', $item['label']),
            ]);
            $pointId = Str::uuid()->toString();
            $pointByLabel[$item['label']] = $pointId;

            $store->upsert($pointId, $item['vec'], [
                'chunk_id' => $chunk->id,
                'document_id' => $doc->id,
                'category_id' => $cat->id,
                'category_slug' => $cat->slug,
            ]);
        }

        $results = $store->search($queryVec, 3);
        $this->assertCount(3, $results);
        $this->assertSame($pointByLabel['exact'], $results[0]['point_id']);
        $this->assertSame($pointByLabel['partial'], $results[1]['point_id']);
        $this->assertSame($pointByLabel['ortho'], $results[2]['point_id']);
        $this->assertEqualsWithDelta(1.0, $results[0]['score'], 0.0001);
        $this->assertEqualsWithDelta(0.707107, $results[1]['score'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $results[2]['score'], 0.0001);
    }

    public function test_qdrant_upsert_failure_rolls_back_database_transaction_consistently(): void
    {
        config()->set('ai.vector.driver', 'qdrant');
        config()->set('ai.vector.fallback_driver', '');
        config()->set('ai.vector.qdrant.host', 'http://127.0.0.1:6333');
        config()->set('ai.providers.gemini.api_key', 'TEST_AUDIT_KEY_123');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => $this->normalizedVector(768, [0 => 1.0])],
            ], 200),
            'http://127.0.0.1:6333/collections' => Http::response(['result' => ['collections' => []]], 200),
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks' => Http::response([
                'result' => ['config' => ['params' => ['vectors' => ['size' => 768, 'distance' => 'Cosine']]]],
            ], 200),
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks/points/delete*' => Http::response(['status' => 'ok'], 200),
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks/points?wait=true' => Http::sequence()
                ->push(['status' => 'ok', 'result' => ['status' => 'completed']], 200)
                ->push(['status' => ['error' => 'Simulated Qdrant failure']], 500)
                ->push(['status' => ['error' => 'Simulated Qdrant failure']], 500)
                ->push(['status' => ['error' => 'Simulated Qdrant failure']], 500),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->where('slug', 'fasilitas')->firstOrFail();

        $doc = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Gedung Asrama Uji Konsistensi',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Informasi resmi gedung asrama santri Bahrul Maghfiroh.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $service = app(KnowledgeIndexingService::class);
        $service->indexDocument($doc);

        $doc->refresh();
        $initialChunkId = $doc->chunks()->firstOrFail()->id;
        $initialPointId = $doc->chunks()->firstOrFail()->vector_point_id;

        // Re-index dengan konten sama, namun Qdrant upsert gagal -> DB harus rollback ke chunk lama
        try {
            $service->indexDocument($doc);
            $this->fail('Seharusnya melempar VectorStoreException saat upsert Qdrant gagal.');
        } catch (VectorStoreException $e) {
            $this->assertStringContainsString('HTTP 500', $e->getMessage());
        }

        $doc->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_FAILED, $doc->processing_status);
        $this->assertDatabaseHas('knowledge_chunks', [
            'id' => $initialChunkId,
            'vector_point_id' => $initialPointId,
        ]);
    }

    public function test_changed_document_purges_stale_chunks_when_edited_or_when_reindexing_fails(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_AUDIT_KEY_123');

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push(['embedding' => ['values' => $this->normalizedVector(768, [0 => 1.0])]], 200)
                ->push(['error' => ['message' => 'Simulated rate limit']], 429)
                ->push(['error' => ['message' => 'Simulated rate limit']], 429)
                ->push(['error' => ['message' => 'Simulated rate limit']], 429),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->where('slug', 'pendaftaran')->firstOrFail();

        $doc = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Informasi Biaya Pendaftaran',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Informasi lama biaya pendaftaran tahun ajaran sebelumnya.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        // 1. Indeks awal berhasil
        $this->actingAs($admin)->post(route('admin.knowledge.index-document', $doc));
        $this->assertDatabaseCount('knowledge_chunks', 1);

        // 2. Isi dokumen berubah langsung, lalu re-indexing gagal -> chunk kedaluwarsa wajib dibersihkan
        $doc->update([
            'raw_content' => 'Informasi terbaru biaya pendaftaran tahun ajaran baru yang sudah direvisi.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.knowledge.index-document', $doc))
            ->assertSessionHasErrors('indexing');

        $doc->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_FAILED, $doc->processing_status);
        $this->assertSame(0, $doc->chunk_count);
        $this->assertDatabaseCount('knowledge_chunks', 0);
        $this->assertDatabaseCount('knowledge_embeddings', 0);

        $searchResults = (new MysqlVectorStore)->search($this->normalizedVector(768, [0 => 1.0]), 5);
        $this->assertCount(0, $searchResults);
    }

    public function test_deactivating_document_or_category_cleans_vectors_and_excludes_from_search(): void
    {
        config()->set('ai.vector.driver', 'mysql');
        config()->set('ai.providers.gemini.api_key', 'TEST_AUDIT_KEY_123');

        $vec = $this->normalizedVector(768, [0 => 1.0]);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => $vec],
            ], 200),
        ]);

        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $cat = FaqCategory::query()->where('slug', 'kegiatan')->firstOrFail();

        $doc = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Jadwal Kegiatan Santri',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Jadwal rutin kegiatan santri pondok pesantren setiap hari.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $this->actingAs($admin)->post(route('admin.knowledge.index-document', $doc));
        $this->assertCount(1, (new MysqlVectorStore)->search($vec, 5));

        // Nonaktifkan kategori via dashboard -> vector dokumen di kategori tsb ikut dibersihkan
        $this->actingAs($admin)->put(route('admin.categories.update', $cat), [
            'name' => $cat->name,
            'slug' => $cat->slug,
            'description' => $cat->description,
            'sort_order' => $cat->sort_order,
            'is_active' => '0',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseCount('knowledge_chunks', 0);
        $this->assertDatabaseCount('knowledge_embeddings', 0);
        $this->assertSame(KnowledgeDocument::STATUS_PENDING, $doc->fresh()->processing_status);
        $this->assertCount(0, (new MysqlVectorStore)->search($vec, 5));

        // Aktifkan kembali kategori, indeks ulang, lalu nonaktifkan dokumen via update -> vector ikut dibersihkan
        $this->actingAs($admin)->put(route('admin.categories.update', $cat), [
            'name' => $cat->name,
            'slug' => $cat->slug,
            'description' => $cat->description,
            'sort_order' => $cat->sort_order,
            'is_active' => '1',
        ]);
        $this->actingAs($admin)->post(route('admin.knowledge.index-document', $doc));
        $this->assertCount(1, (new MysqlVectorStore)->search($vec, 5));

        $this->actingAs($admin)->put(route('admin.knowledge.update', $doc), [
            'category_id' => $cat->id,
            'title' => $doc->title,
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => $doc->raw_content,
            'is_active' => '0',
        ]);

        $this->assertDatabaseCount('knowledge_chunks', 0);
        $this->assertCount(0, (new MysqlVectorStore)->search($vec, 5));
    }

    public function test_qdrant_search_always_filters_only_active_records(): void
    {
        Http::fake([
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks/points/search' => Http::response([
                'status' => 'ok',
                'result' => [],
            ], 200),
        ]);

        $qdrant = app(\App\Services\VectorStore\QdrantVectorStore::class);
        $qdrant->search($this->normalizedVector(768, [0 => 1.0]), 5);

        Http::assertSent(function (Request $request): bool {
            $must = $request['filter']['must'] ?? [];
            foreach ($must as $condition) {
                if (($condition['key'] ?? null) === 'is_active' && ($condition['match']['value'] ?? null) === true) {
                    return true;
                }
            }

            return false;
        });
    }

    public function test_job_retry_backoff_uniqueness_and_secret_redaction_on_failure(): void
    {
        config()->set('ai.providers.gemini.api_key', 'SECRET_GEMINI_XYZ_999');
        config()->set('ai.vector.qdrant.api_key', 'SECRET_QDRANT_XYZ_999');

        $this->seed(FaqCategorySeeder::class);
        $cat = FaqCategory::query()->firstOrFail();
        $doc = KnowledgeDocument::query()->create([
            'category_id' => $cat->id,
            'title' => 'Uji Job Failed Hook',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Konten uji hook kegagalan antrean.',
            'is_active' => true,
        ]);

        $job = new IndexKnowledgeDocumentJob($doc->id);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 60], $job->backoff);
        $this->assertSame("index-kb-doc-{$doc->id}", $job->uniqueId());

        $job->failed(new RuntimeException('Upstream error with keys SECRET_GEMINI_XYZ_999 and SECRET_QDRANT_XYZ_999'));

        $doc->refresh();
        $this->assertSame(KnowledgeDocument::STATUS_FAILED, $doc->processing_status);
        $this->assertStringNotContainsString('SECRET_GEMINI_XYZ_999', (string) $doc->error_message);
        $this->assertStringNotContainsString('SECRET_QDRANT_XYZ_999', (string) $doc->error_message);
        $this->assertStringContainsString('[REDACTED]', (string) $doc->error_message);
    }

    /**
     * @param  array<int, float>  $weights
     * @return list<float>
     */
    private function normalizedVector(int $dimensions, array $weights): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        foreach ($weights as $index => $value) {
            $vector[$index] = $value;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v) => $v * $v, $vector)));
        if ($norm > 0.0) {
            $vector = array_map(static fn (float $v) => $v / $norm, $vector);
        }

        return $vector;
    }
}
