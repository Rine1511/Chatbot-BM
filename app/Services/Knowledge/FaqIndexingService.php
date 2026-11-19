<?php

namespace App\Services\Knowledge;

use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\VectorStoreInterface;
use App\Exceptions\EmbeddingProviderException;
use App\Models\Faq;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEmbedding;
use App\Services\VectorStore\QdrantVectorStore;
use App\Services\VectorStore\VectorStoreManager;
use App\Support\SecretRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class FaqIndexingService
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
        private readonly EmbeddingProviderInterface $embeddingProvider,
        private readonly VectorStoreManager $vectorStoreManager,
    ) {}

    public function indexFaq(
        Faq $faq,
        ?VectorStoreInterface $vectorStoreOverride = null,
        ?EmbeddingProviderInterface $embeddingOverride = null,
    ): Faq {
        $faq->refresh();
        $faq->loadMissing('category');

        if (! $faq->is_active || ! $faq->category?->is_active) {
            $this->cleanupFaqVectors($faq, $vectorStoreOverride);
            $faq->update(['embedding_status' => Faq::EMBEDDING_PENDING]);

            return $faq->fresh(['category']);
        }

        $content = $this->formatFaqContent($faq);
        $contentHash = hash('sha256', $content);
        $tokenCount = $this->normalizer->estimateTokens($content);

        $embeddingProvider = $embeddingOverride ?? $this->embeddingProvider;
        $vectorStore = $vectorStoreOverride ?? $this->vectorStoreManager->resolveWithFallback();

        $dimensions = $embeddingProvider->dimensions();
        $embeddingModel = $embeddingProvider->modelName();

        try {
            $vectorStore->ensureCollection($dimensions);
            $vector = $embeddingProvider->embed($content, 'RETRIEVAL_DOCUMENT');
            if (count($vector) !== $dimensions) {
                throw new EmbeddingProviderException(
                    sprintf('Dimensi embedding FAQ (%d) tidak cocok dengan konfigurasi (%d).', count($vector), $dimensions)
                );
            }

            $oldPointIds = $faq->chunks()
                ->whereNotNull('vector_point_id')
                ->pluck('vector_point_id')
                ->map(static fn ($id) => (string) $id)
                ->all();

            $newPointId = Str::uuid()->toString();

            DB::beginTransaction();
            $externalUpserted = false;

            try {
                $faq->chunks()->delete();

                $chunkModel = KnowledgeChunk::query()->create([
                    'document_id' => null,
                    'faq_id' => $faq->id,
                    'chunk_index' => 0,
                    'content' => $content,
                    'token_count' => $tokenCount,
                    'content_hash' => $contentHash,
                    'vector_point_id' => $newPointId,
                    'metadata' => [
                        'faq_id' => $faq->id,
                        'faq_question' => $faq->question,
                        'category_id' => $faq->category_id,
                        'category_slug' => $faq->category?->slug,
                        'source_type' => 'faq',
                        'source_url' => $faq->source_url,
                        'vector_driver' => $vectorStore->driverName(),
                    ],
                ]);

                KnowledgeEmbedding::query()->create([
                    'chunk_id' => $chunkModel->id,
                    'embedding_model' => $embeddingModel,
                    'dimensions' => $dimensions,
                    'vector_data' => $vector,
                ]);

                $faq->update(['embedding_status' => Faq::EMBEDDING_INDEXED]);

                if ($vectorStore->driverName() !== 'mysql') {
                    $payload = [
                        'document_id' => null,
                        'faq_id' => $faq->id,
                        'chunk_id' => $chunkModel->id,
                        'category_id' => $faq->category_id,
                        'category_slug' => (string) ($faq->category?->slug ?? ''),
                        'source_type' => 'faq',
                        'source_label' => 'FAQ Resmi',
                        'document_title' => $faq->question,
                        'source_url' => $faq->source_url,
                        'chunk_index' => 0,
                        'total_chunks' => 1,
                        'content_hash' => $contentHash,
                        'content' => $content,
                        'embedding_model' => $embeddingModel,
                        'vector_driver' => $vectorStore->driverName(),
                        'is_active' => true,
                    ];

                    if ($vectorStore instanceof QdrantVectorStore) {
                        $vectorStore->upsertBatch([[
                            'id' => $newPointId,
                            'vector' => $vector,
                            'payload' => $payload,
                        ]]);
                    } else {
                        $vectorStore->upsert($newPointId, $vector, $payload);
                    }

                    $externalUpserted = true;
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();

                if ($externalUpserted || $vectorStore->driverName() !== 'mysql') {
                    try {
                        $vectorStore->deleteByIds([$newPointId]);
                    } catch (Throwable) {
                        // Best-effort kompensasi
                    }
                }

                throw $e;
            }

            if ($vectorStore->driverName() !== 'mysql' && $oldPointIds !== []) {
                try {
                    $vectorStore->deleteByIds($oldPointIds);
                } catch (Throwable $e) {
                    $safeMsg = SecretRedactor::redact($e->getMessage());
                    Log::warning("Gagal membersihkan point lama FAQ #{$faq->id}: {$safeMsg}");
                }
            }

            return $faq->fresh(['category', 'chunks.embedding']);
        } catch (Throwable $e) {
            $faq->update(['embedding_status' => Faq::EMBEDDING_FAILED]);

            throw $e;
        }
    }

    public function cleanupFaqVectors(
        Faq $faq,
        ?VectorStoreInterface $vectorStoreOverride = null,
    ): void {
        $pointIds = $faq->chunks()
            ->whereNotNull('vector_point_id')
            ->pluck('vector_point_id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        if ($pointIds !== []) {
            $storesToClean = [];
            if ($vectorStoreOverride) {
                $storesToClean[] = $vectorStoreOverride;
            } else {
                $storesToClean[] = $this->vectorStoreManager->driver('mysql');

                if (config('ai.vector.driver') === 'qdrant') {
                    $qdrant = $this->vectorStoreManager->driver('qdrant');
                    if ($qdrant->isAvailable()) {
                        $storesToClean[] = $qdrant;
                    }
                }
            }

            foreach ($storesToClean as $store) {
                try {
                    $store->deleteByIds($pointIds);
                } catch (Throwable $e) {
                    $safeMsg = SecretRedactor::redact($e->getMessage());
                    Log::warning("Gagal menghapus vector FAQ #{$faq->id} pada {$store->driverName()}: {$safeMsg}");
                }
            }
        }

        DB::transaction(function () use ($faq): void {
            $faq->chunks()->delete();
        });
    }

    public function indexPendingFaqs(): int
    {
        $faqs = Faq::query()
            ->forChatbot()
            ->whereIn('embedding_status', [Faq::EMBEDDING_PENDING, Faq::EMBEDDING_FAILED])
            ->get();

        $count = 0;
        foreach ($faqs as $faq) {
            $this->indexFaq($faq);
            $count++;
        }

        return $count;
    }

    private function formatFaqContent(Faq $faq): string
    {
        $parts = [
            'Pertanyaan: '.$this->normalizer->normalize($faq->question),
        ];

        $variations = $faq->variationsList();
        if ($variations !== []) {
            $parts[] = 'Variasi Pertanyaan: '.$this->normalizer->normalize(implode('; ', $variations));
        }

        $keywords = $this->normalizer->normalize($faq->keywords);
        if ($keywords !== '') {
            $parts[] = 'Kata Kunci: '.$keywords;
        }

        if ($faq->category) {
            $parts[] = 'Kategori: '.$this->normalizer->normalize($faq->category->name);
        }

        $parts[] = 'Jawaban: '.$this->normalizer->normalize($faq->answer);

        if ($faq->source_url) {
            $parts[] = 'Tautan Resmi: '.trim($faq->source_url);
        }

        return implode("\n", $parts);
    }
}
