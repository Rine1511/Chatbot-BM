<?php

namespace App\Services\Knowledge;

use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\VectorStoreInterface;
use App\Exceptions\DocumentExtractionException;
use App\Exceptions\EmbeddingProviderException;
use App\Exceptions\VectorStoreException;
use App\Jobs\IndexKnowledgeDocumentJob;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeEmbedding;
use App\Services\VectorStore\QdrantVectorStore;
use App\Services\VectorStore\VectorStoreManager;
use App\Support\SecretRedactor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class KnowledgeIndexingService
{
    public function __construct(
        private readonly DocumentExtractor $extractor,
        private readonly TextChunker $chunker,
        private readonly EmbeddingProviderInterface $embeddingProvider,
        private readonly VectorStoreManager $vectorStoreManager,
    ) {}

    /**
     * Kirim dokumen ke pipeline indexing (antrean atau sinkron).
     */
    public function dispatch(KnowledgeDocument $document, bool $async = false): string
    {
        $document->loadMissing('category');

        if (! $document->is_active || ! $document->category?->is_active) {
            throw new DocumentExtractionException(
                'Dokumen tidak dapat diproses karena status dokumen atau kategorinya sedang nonaktif.'
            );
        }

        if ($document->processing_status === KnowledgeDocument::STATUS_PROCESSING) {
            throw new DocumentExtractionException(
                'Dokumen ini sedang berada dalam antrean atau proses indexing.'
            );
        }

        $lock = Cache::lock("kb-index-doc-{$document->id}", 120);
        if (! $lock->get()) {
            throw new DocumentExtractionException(
                'Dokumen sedang diproses oleh pekerja lain. Silakan tunggu hingga proses selesai.'
            );
        }
        $lock->release();

        if ($async && config('queue.default') !== 'sync') {
            $document->update([
                'processing_status' => KnowledgeDocument::STATUS_PROCESSING,
                'error_message' => null,
            ]);

            IndexKnowledgeDocumentJob::dispatch($document->id);

            return 'queued';
        }

        IndexKnowledgeDocumentJob::dispatchSync($document->id);

        return 'synced';
    }

    /**
     * Jalankan pipeline lengkap: ekstraksi -> chunking -> embedding -> vector indexing.
     */
    public function indexDocument(
        KnowledgeDocument $document,
        ?VectorStoreInterface $vectorStoreOverride = null,
        ?EmbeddingProviderInterface $embeddingOverride = null,
    ): KnowledgeDocument {
        $lock = Cache::lock("kb-index-doc-{$document->id}", 120);

        if (! $lock->get()) {
            throw new DocumentExtractionException(
                'Dokumen sedang diproses oleh pekerja lain. Silakan tunggu hingga proses selesai.'
            );
        }

        $documentStaleOrChanged = false;

        try {
            $document->refresh();
            $document->loadMissing('category');
            $previousStatus = $document->processing_status;

            if (! $document->is_active || ! $document->category?->is_active) {
                $this->cleanupDocumentVectors($document, $vectorStoreOverride);

                $reason = 'Dokumen atau kategorinya berstatus nonaktif sehingga tidak diindeks.';
                $this->markFailed($document, $reason);

                throw new DocumentExtractionException($reason);
            }

            $documentStaleOrChanged = $previousStatus === KnowledgeDocument::STATUS_PENDING;

            $document->update([
                'processing_status' => KnowledgeDocument::STATUS_PROCESSING,
                'error_message' => null,
            ]);

            $embeddingProvider = $embeddingOverride ?? $this->embeddingProvider;
            $vectorStore = $vectorStoreOverride ?? $this->vectorStoreManager->resolveWithFallback();

            // 1. Ekstraksi & normalisasi konten
            $normalizedText = $this->extractor->extract($document);
            $documentContentHash = hash('sha256', $normalizedText);

            // Deteksi apakah konten atau kategori berubah dibanding chunk lama agar chunk kedaluwarsa tidak tertinggal bila gagal
            $firstOldChunk = $document->chunks()->orderBy('chunk_index')->first();
            if ($firstOldChunk) {
                $oldDocHash = $firstOldChunk->metadata['document_content_hash'] ?? null;
                $oldCategoryId = $firstOldChunk->metadata['category_id'] ?? null;
                if ($oldDocHash !== $documentContentHash || (int) $oldCategoryId !== (int) $document->category_id) {
                    $documentStaleOrChanged = true;
                }
            }

            // 2. Chunking dengan ukuran & overlap terkonfigurasi
            $chunks = $this->chunker->chunk($normalizedText);
            if ($chunks === []) {
                throw new DocumentExtractionException(
                    'Dokumen tidak menghasilkan potongan teks (chunks) yang valid.'
                );
            }

            // 3. Pastikan collection/storage siap & hasilkan embedding untuk setiap chunk
            $dimensions = $embeddingProvider->dimensions();
            $embeddingModel = $embeddingProvider->modelName();
            $vectorStore->ensureCollection($dimensions);

            $prepared = [];
            $totalChunks = count($chunks);

            foreach ($chunks as $chunk) {
                $vector = $embeddingProvider->embed($chunk['content'], 'RETRIEVAL_DOCUMENT');
                if (count($vector) !== $dimensions) {
                    throw new EmbeddingProviderException(
                        sprintf('Dimensi embedding (%d) tidak cocok dengan konfigurasi (%d).', count($vector), $dimensions)
                    );
                }

                $prepared[] = [
                    'chunk_index' => $chunk['chunk_index'],
                    'content' => $chunk['content'],
                    'token_count' => $chunk['token_count'],
                    'content_hash' => $chunk['content_hash'],
                    'point_id' => Str::uuid()->toString(),
                    'vector' => $vector,
                    'total_chunks' => $totalChunks,
                ];
            }

            $oldPointIds = $document->chunks()
                ->whereNotNull('vector_point_id')
                ->pluck('vector_point_id')
                ->map(static fn ($id) => (string) $id)
                ->all();

            $newPointIds = array_column($prepared, 'point_id');

            // 4. Transaksi database + sinkronisasi Qdrant dengan kompensasi rollback atomik
            DB::beginTransaction();
            $externalUpserted = false;

            try {
                $document->chunks()->delete();

                $qdrantBatch = [];

                foreach ($prepared as $item) {
                    $chunkModel = KnowledgeChunk::query()->create([
                        'document_id' => $document->id,
                        'faq_id' => null,
                        'chunk_index' => $item['chunk_index'],
                        'content' => $item['content'],
                        'token_count' => $item['token_count'],
                        'content_hash' => $item['content_hash'],
                        'vector_point_id' => $item['point_id'],
                        'metadata' => [
                            'document_id' => $document->id,
                            'document_title' => $document->title,
                            'document_content_hash' => $documentContentHash,
                            'category_id' => $document->category_id,
                            'category_slug' => $document->category?->slug,
                            'source_type' => $document->source_type,
                            'source_label' => $document->source_label,
                            'chunk_index' => $item['chunk_index'],
                            'total_chunks' => $item['total_chunks'],
                            'vector_driver' => $vectorStore->driverName(),
                        ],
                    ]);

                    KnowledgeEmbedding::query()->create([
                        'chunk_id' => $chunkModel->id,
                        'embedding_model' => $embeddingModel,
                        'dimensions' => $dimensions,
                        'vector_data' => $item['vector'],
                    ]);

                    if ($vectorStore->driverName() !== 'mysql') {
                        $qdrantBatch[] = [
                            'id' => $item['point_id'],
                            'vector' => $item['vector'],
                            'payload' => $this->buildPayload(
                                $document,
                                $item,
                                $embeddingModel,
                                $vectorStore->driverName(),
                                $chunkModel->id,
                                $documentContentHash
                            ),
                        ];
                    }
                }

                $document->update([
                    'raw_content' => $normalizedText,
                    'processing_status' => KnowledgeDocument::STATUS_INDEXED,
                    'error_message' => null,
                    'chunk_count' => count($prepared),
                    'last_indexed_at' => now(),
                ]);

                if ($vectorStore->driverName() !== 'mysql' && $qdrantBatch !== []) {
                    if ($vectorStore instanceof QdrantVectorStore) {
                        $vectorStore->upsertBatch($qdrantBatch);
                    } else {
                        foreach ($qdrantBatch as $point) {
                            $vectorStore->upsert($point['id'], $point['vector'], $point['payload']);
                        }
                    }
                    $externalUpserted = true;
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();

                if ($externalUpserted || $vectorStore->driverName() !== 'mysql') {
                    try {
                        $vectorStore->deleteByIds($newPointIds);
                    } catch (Throwable) {
                        // Best-effort kompensasi menghapus titik baru bila commit DB gagal
                    }
                }

                throw $e;
            }

            // 5. Bersihkan point lama pada vector store eksternal setelah transaksi sukses
            if ($vectorStore->driverName() !== 'mysql' && $oldPointIds !== []) {
                try {
                    $vectorStore->deleteByIds($oldPointIds);
                } catch (Throwable $e) {
                    $safeMsg = SecretRedactor::redact($e->getMessage());
                    Log::warning("Gagal membersihkan point lama {$vectorStore->driverName()} untuk dokumen #{$document->id}: {$safeMsg}");
                }
            }

            return $document->fresh(['category', 'chunks.embedding']);
        } catch (DocumentExtractionException|EmbeddingProviderException|VectorStoreException $e) {
            if ($documentStaleOrChanged) {
                $this->cleanupDocumentVectors($document, $vectorStoreOverride);
            }
            $this->markFailed($document, $e->getMessage());

            throw $e;
        } catch (Throwable $e) {
            if ($documentStaleOrChanged) {
                $this->cleanupDocumentVectors($document, $vectorStoreOverride);
            }
            $this->markFailed($document, 'Terjadi kesalahan saat memproses dokumen: '.$e->getMessage());

            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * Bersihkan seluruh chunk, embedding, dan titik vector eksternal untuk sebuah dokumen.
     */
    public function cleanupDocumentVectors(
        KnowledgeDocument $document,
        ?VectorStoreInterface $vectorStoreOverride = null,
    ): void {
        $pointIds = $document->chunks()
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
                    Log::warning("Gagal menghapus vector pada driver {$store->driverName()} untuk dokumen #{$document->id}: {$safeMsg}");
                }
            }
        }

        DB::transaction(function () use ($document): void {
            $document->chunks()->delete();
            $document->update([
                'chunk_count' => 0,
                'last_indexed_at' => null,
            ]);
        });
    }

    private function markFailed(KnowledgeDocument $document, string $message): void
    {
        $safeMessage = Str::limit(trim(SecretRedactor::redact($message)), 2000);

        $document->update([
            'processing_status' => KnowledgeDocument::STATUS_FAILED,
            'error_message' => $safeMessage,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function buildPayload(
        KnowledgeDocument $document,
        array $item,
        string $embeddingModel,
        string $driverName,
        ?int $chunkId = null,
        ?string $documentContentHash = null,
    ): array {
        return [
            'document_id' => $document->id,
            'faq_id' => null,
            'chunk_id' => $chunkId ?? 0,
            'category_id' => $document->category_id,
            'category_slug' => (string) ($document->category?->slug ?? ''),
            'source_type' => $document->source_type,
            'source_label' => $document->source_label,
            'document_title' => $document->title,
            'document_content_hash' => $documentContentHash,
            'chunk_index' => $item['chunk_index'],
            'total_chunks' => $item['total_chunks'],
            'content_hash' => $item['content_hash'],
            'content' => $item['content'],
            'embedding_model' => $embeddingModel,
            'vector_driver' => $driverName,
            'is_active' => true,
        ];
    }
}
