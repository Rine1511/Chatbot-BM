<?php

namespace Tests\Feature;

use App\Exceptions\EmbeddingProviderException;
use App\Exceptions\VectorStoreException;
use App\Models\FaqCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\User;
use App\Services\AI\GeminiEmbeddingProvider;
use App\Services\VectorStore\MysqlVectorStore;
use App\Services\VectorStore\QdrantVectorStore;
use Database\Seeders\FaqCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class GeminiEmbeddingAndVectorStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_gemini_embedding_provider_sends_expected_payload_and_returns_768_dimensions(): void
    {
        $fakeVector = $this->makeVector(768, 0.25);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => [
                    'values' => $fakeVector,
                ],
            ], 200),
        ]);

        $provider = new GeminiEmbeddingProvider(
            apiKey: 'AIzaTestSecretKey98765',
            baseUrl: 'https://generativelanguage.googleapis.com/v1beta',
            model: 'gemini-embedding-001',
            dimensions: 768,
            timeout: 10,
            retryTimes: 0,
            retrySleepMs: 0,
        );

        $vector = $provider->embed('Profil Pondok Pesantren Bahrul Maghfiroh Kota Malang');

        $this->assertCount(768, $vector);
        $this->assertSame(768, $provider->dimensions());
        $this->assertSame('gemini-embedding-001', $provider->modelName());

        Http::assertSent(function (Request $request): bool {
            return str_contains($request->url(), '/models/gemini-embedding-001:embedContent')
                && $request->hasHeader('x-goog-api-key', 'AIzaTestSecretKey98765')
                && ! str_contains($request->url(), 'AIzaTestSecretKey98765')
                && ($request['outputDimensionality'] ?? null) === 768
                && ($request['taskType'] ?? null) === 'RETRIEVAL_DOCUMENT';
        });
    }

    public function test_gemini_embedding_handles_rate_limit_timeout_dimension_mismatch_and_never_leaks_key(): void
    {
        $secretKey = 'SUPER_SECRET_GEMINI_KEY_XYZ';

        // 1. Missing API key
        $emptyProvider = new GeminiEmbeddingProvider(apiKey: '', retryTimes: 0);
        try {
            $emptyProvider->embed('Uji kunci kosong');
            $this->fail('Harus gagal jika API key kosong.');
        } catch (EmbeddingProviderException $e) {
            $this->assertStringContainsString('GEMINI_API_KEY', $e->getMessage());
        }

        // 2. Rate limit HTTP 429
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Quota exceeded']], 429),
        ]);
        $rateProvider = new GeminiEmbeddingProvider(apiKey: $secretKey, retryTimes: 0, retrySleepMs: 0);
        try {
            $rateProvider->embed('Uji rate limit');
            $this->fail('Harus melempar exception pada HTTP 429.');
        } catch (EmbeddingProviderException $e) {
            $this->assertStringContainsString('rate limit', strtolower($e->getMessage()));
            $this->assertStringNotContainsString($secretKey, $e->getMessage());
        }
    }

    public function test_gemini_embedding_rejects_mismatched_dimensions_without_leaking_key(): void
    {
        $secretKey = 'SUPER_SECRET_GEMINI_KEY_DIM';
        $provider = new GeminiEmbeddingProvider(apiKey: $secretKey, retryTimes: 0, retrySleepMs: 0);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'embedding' => ['values' => $this->makeVector(256, 0.1)],
            ], 200),
        ]);

        try {
            $provider->embed('Uji dimensi salah');
            $this->fail('Harus menolak dimensi yang tidak sesuai.');
        } catch (EmbeddingProviderException $e) {
            $this->assertStringContainsString('768', $e->getMessage());
            $this->assertStringNotContainsString($secretKey, $e->getMessage());
        }
    }

    public function test_gemini_embedding_handles_connection_timeout_without_leaking_key(): void
    {
        $secretKey = 'SUPER_SECRET_GEMINI_KEY_TIMEOUT';
        $provider = new GeminiEmbeddingProvider(apiKey: $secretKey, retryTimes: 0, retrySleepMs: 0);

        Http::fake(function () {
            throw new ConnectionException('cURL error 28: Operation timed out');
        });

        try {
            $provider->embed('Uji timeout');
            $this->fail('Harus melempar exception pada timeout.');
        } catch (EmbeddingProviderException $e) {
            $this->assertStringContainsString('timeout', strtolower($e->getMessage()));
            $this->assertStringNotContainsString($secretKey, $e->getMessage());
        }
    }

    public function test_mysql_vector_store_upsert_search_filter_and_delete(): void
    {
        $this->seed(FaqCategorySeeder::class);
        $admin = User::factory()->create();
        $catProfil = FaqCategory::query()->where('slug', 'profil-pondok')->firstOrFail();
        $catBangunan = FaqCategory::query()->where('slug', 'informasi-bangunan')->firstOrFail();

        $activeDoc = KnowledgeDocument::query()->create([
            'category_id' => $catProfil->id,
            'title' => 'Sejarah Pondok',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Konten sejarah pondok.',
            'is_active' => true,
            'uploaded_by' => $admin->id,
        ]);

        $inactiveDoc = KnowledgeDocument::query()->create([
            'category_id' => $catBangunan->id,
            'title' => 'Gedung Arsip Nonaktif',
            'source_type' => KnowledgeDocument::SOURCE_TEXT,
            'raw_content' => 'Gedung nonaktif tidak boleh muncul di hasil pencarian.',
            'is_active' => false,
            'uploaded_by' => $admin->id,
        ]);

        $chunkA = KnowledgeChunk::query()->create([
            'document_id' => $activeDoc->id,
            'chunk_index' => 0,
            'content' => 'Sejarah berdirinya Pondok Pesantren Bahrul Maghfiroh di Malang.',
            'token_count' => 12,
            'content_hash' => hash('sha256', 'chunkA'),
            'metadata' => ['document_title' => 'Sejarah Pondok'],
        ]);

        $chunkB = KnowledgeChunk::query()->create([
            'document_id' => $activeDoc->id,
            'chunk_index' => 1,
            'content' => 'Visi dan misi pendidikan karakter santri.',
            'token_count' => 8,
            'content_hash' => hash('sha256', 'chunkB'),
            'metadata' => ['document_title' => 'Sejarah Pondok'],
        ]);

        $chunkInactive = KnowledgeChunk::query()->create([
            'document_id' => $inactiveDoc->id,
            'chunk_index' => 0,
            'content' => 'Dokumen nonaktif.',
            'token_count' => 4,
            'content_hash' => hash('sha256', 'chunkInactive'),
        ]);

        $store = new MysqlVectorStore;
        $this->assertTrue($store->isAvailable());
        $store->ensureCollection(768);

        $pointA = Str::uuid()->toString();
        $pointB = Str::uuid()->toString();
        $pointInactive = Str::uuid()->toString();

        $vecA = $this->makeBasisVector(768, 0);
        $vecB = $this->makeBasisVector(768, 1);

        $store->upsert($pointA, $vecA, [
            'chunk_id' => $chunkA->id,
            'document_id' => $activeDoc->id,
            'category_id' => $catProfil->id,
            'category_slug' => 'profil-pondok',
            'embedding_model' => 'gemini-embedding-001',
        ]);
        $store->upsert($pointB, $vecB, [
            'chunk_id' => $chunkB->id,
            'document_id' => $activeDoc->id,
            'category_id' => $catProfil->id,
            'category_slug' => 'profil-pondok',
            'embedding_model' => 'gemini-embedding-001',
        ]);
        $store->upsert($pointInactive, $vecA, [
            'chunk_id' => $chunkInactive->id,
            'document_id' => $inactiveDoc->id,
            'category_id' => $catBangunan->id,
            'category_slug' => 'informasi-bangunan',
            'embedding_model' => 'gemini-embedding-001',
        ]);

        $results = $store->search($vecA, 5, ['category_slug' => 'profil-pondok']);
        $this->assertCount(2, $results);
        $this->assertSame($pointA, $results[0]['point_id']);
        $this->assertGreaterThan(0.99, $results[0]['score']);
        $this->assertSame(0.0, $results[1]['score']);

        // Pastikan dokumen nonaktif tidak pernah muncul meski vektornya identik
        $allResults = $store->search($vecA, 5);
        $returnedPointIds = array_column($allResults, 'point_id');
        $this->assertNotContains($pointInactive, $returnedPointIds);

        // Hapus vector point A
        $store->deleteByIds([$pointA]);
        $afterDelete = $store->search($vecA, 5);
        $this->assertNotContains($pointA, array_column($afterDelete, 'point_id'));
    }

    public function test_qdrant_vector_store_simulated_collection_upsert_search_and_delete(): void
    {
        $pointId = Str::uuid()->toString();
        $vector = $this->makeBasisVector(768, 0);

        Http::fake([
            'http://127.0.0.1:6333/collections' => Http::response(['result' => ['collections' => []]], 200),
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks' => Http::sequence()
                ->push(['status' => ['error' => 'Not found']], 404)
                ->push(['result' => ['config' => ['params' => ['vectors' => ['size' => 768, 'distance' => 'Cosine']]]]], 200)
                ->push(['result' => true], 200),
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks/points*' => Http::sequence()
                ->push(['status' => 'ok', 'result' => ['status' => 'completed']], 200)
                ->push([
                    'status' => 'ok',
                    'result' => [
                        [
                            'id' => $pointId,
                            'score' => 0.985,
                            'payload' => [
                                'document_id' => 1,
                                'chunk_id' => 10,
                                'category_id' => 6,
                                'category_slug' => 'informasi-bangunan',
                            ],
                        ],
                    ],
                ], 200)
                ->push(['status' => 'ok', 'result' => ['status' => 'completed']], 200),
        ]);

        $qdrant = new QdrantVectorStore(
            host: 'http://127.0.0.1:6333',
            apiKey: 'qdrant-secret-key',
            collection: 'bm_knowledge_chunks',
            vectorSize: 768,
            distance: 'Cosine',
            timeout: 5,
        );

        $this->assertTrue($qdrant->isAvailable());
        $qdrant->ensureCollection(768);
        $qdrant->upsert($pointId, $vector, [
            'document_id' => 1,
            'chunk_id' => 10,
            'category_id' => 6,
            'category_slug' => 'informasi-bangunan',
            'source_type' => 'text',
        ]);

        $searchResults = $qdrant->search($vector, 3, ['category_slug' => 'informasi-bangunan']);
        $this->assertCount(1, $searchResults);
        $this->assertSame($pointId, $searchResults[0]['point_id']);
        $this->assertSame(0.985, $searchResults[0]['score']);

        $qdrant->deleteByIds([$pointId]);

        Http::assertSent(function (Request $request): bool {
            return $request->hasHeader('api-key', 'qdrant-secret-key');
        });
    }

    public function test_qdrant_vector_store_rejects_mismatched_collection_dimensions(): void
    {
        Http::fake([
            'http://127.0.0.1:6333/collections/bm_knowledge_chunks' => Http::response([
                'result' => [
                    'config' => [
                        'params' => [
                            'vectors' => ['size' => 1536, 'distance' => 'Cosine'],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $qdrant = new QdrantVectorStore(
            host: 'http://127.0.0.1:6333',
            collection: 'bm_knowledge_chunks',
            vectorSize: 768,
        );

        $this->expectException(VectorStoreException::class);
        $qdrant->ensureCollection(768);
    }

    /**
     * @return list<float>
     */
    private function makeVector(int $dimensions, float $value): array
    {
        return array_fill(0, $dimensions, $value);
    }

    /**
     * @return list<float>
     */
    private function makeBasisVector(int $dimensions, int $activeIndex): array
    {
        $vector = array_fill(0, $dimensions, 0.0);
        $vector[$activeIndex] = 1.0;

        return $vector;
    }
}
