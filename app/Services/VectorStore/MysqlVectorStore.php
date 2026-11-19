<?php

namespace App\Services\VectorStore;

use App\Contracts\VectorStoreInterface;
use App\Exceptions\VectorStoreException;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeEmbedding;
use Illuminate\Support\Facades\DB;
use Throwable;

class MysqlVectorStore implements VectorStoreInterface
{
    public function isAvailable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function ensureCollection(int $dimensions): void
    {
        if ($dimensions <= 0) {
            throw new VectorStoreException('Dimensi vektor harus lebih besar dari 0.');
        }

        if (! $this->isAvailable()) {
            throw new VectorStoreException('Koneksi database relasional untuk penyimpanan vektor tidak tersedia.');
        }
    }

    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>  $payload
     */
    public function upsert(string $pointId, array $vector, array $payload): void
    {
        $chunkId = isset($payload['chunk_id']) ? (int) $payload['chunk_id'] : 0;
        if ($chunkId <= 0) {
            throw new VectorStoreException('Payload upsert vector harus menyertakan chunk_id yang valid.');
        }

        $expectedDimensions = (int) config('ai.embedding.dimensions', 768);
        if (count($vector) !== $expectedDimensions) {
            throw new VectorStoreException(
                sprintf('Dimensi vektor upsert (%d) tidak cocok dengan konfigurasi (%d).', count($vector), $expectedDimensions)
            );
        }

        $chunk = KnowledgeChunk::query()->find($chunkId);
        if (! $chunk) {
            throw new VectorStoreException("Chunk #{$chunkId} tidak ditemukan saat menyimpan vector ke database.");
        }

        DB::transaction(function () use ($chunk, $pointId, $vector, $payload): void {
            if ($chunk->vector_point_id !== $pointId) {
                $chunk->update(['vector_point_id' => $pointId]);
            }

            KnowledgeEmbedding::query()->updateOrCreate(
                ['chunk_id' => $chunk->id],
                [
                    'embedding_model' => (string) ($payload['embedding_model'] ?? config('ai.embedding.model', 'gemini-embedding-001')),
                    'dimensions' => count($vector),
                    'vector_data' => array_values(array_map('floatval', $vector)),
                ]
            );
        });
    }

    /**
     * @param  list<float>  $queryVector
     * @param  array<string, mixed>  $filters
     * @return array<int, array{point_id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(array $queryVector, int $limit = 5, array $filters = []): array
    {
        $expectedDimensions = (int) config('ai.embedding.dimensions', 768);
        if (count($queryVector) !== $expectedDimensions) {
            throw new VectorStoreException(
                sprintf('Dimensi vektor query (%d) tidak sesuai dengan konfigurasi (%d).', count($queryVector), $expectedDimensions)
            );
        }

        $query = KnowledgeEmbedding::query()
            ->with(['chunk.document.category', 'chunk.faq.category'])
            ->where('dimensions', $expectedDimensions)
            ->whereHas('chunk', function ($chunkQuery) use ($filters): void {
                if (isset($filters['document_id'])) {
                    $chunkQuery->where('document_id', (int) $filters['document_id']);
                }

                $chunkQuery->where(function ($sourceQuery) use ($filters): void {
                    $sourceType = isset($filters['source_type']) ? trim((string) $filters['source_type']) : null;

                    if ($sourceType !== 'faq') {
                        $sourceQuery->whereHas('document', function ($docQuery) use ($filters, $sourceType): void {
                            $docQuery->where('is_active', true)
                                ->whereHas('category', function ($catQuery) use ($filters): void {
                                    $catQuery->where('is_active', true);
                                    if (isset($filters['category_id'])) {
                                        $catQuery->where('id', (int) $filters['category_id']);
                                    }
                                    if (isset($filters['category_slug'])) {
                                        $catQuery->where('slug', (string) $filters['category_slug']);
                                    }
                                });

                            if ($sourceType !== null && $sourceType !== '') {
                                $docQuery->where('source_type', $sourceType);
                            }
                        });
                    }

                    if ($sourceType === null || $sourceType === '' || $sourceType === 'faq') {
                        $method = $sourceType === 'faq' ? 'whereHas' : 'orWhereHas';
                        $sourceQuery->{$method}('faq', function ($faqQuery) use ($filters): void {
                            $faqQuery->where('is_active', true)
                                ->whereHas('category', function ($catQuery) use ($filters): void {
                                    $catQuery->where('is_active', true);
                                    if (isset($filters['category_id'])) {
                                        $catQuery->where('id', (int) $filters['category_id']);
                                    }
                                    if (isset($filters['category_slug'])) {
                                        $catQuery->where('slug', (string) $filters['category_slug']);
                                    }
                                });
                        });
                    }
                });
            });

        $candidates = $query->get();
        $scored = [];

        foreach ($candidates as $embedding) {
            $chunk = $embedding->chunk;
            $vector = $embedding->vector_data;
            if (! $chunk || ! is_array($vector) || count($vector) !== $expectedDimensions) {
                continue;
            }

            $score = $this->cosineSimilarity($queryVector, $vector);
            $category = $chunk->document?->category ?? $chunk->faq?->category;

            $payload = array_merge($chunk->metadata ?? [], [
                'chunk_id' => $chunk->id,
                'document_id' => $chunk->document_id,
                'faq_id' => $chunk->faq_id,
                'chunk_index' => $chunk->chunk_index,
                'category_id' => $category?->id,
                'category_slug' => $category?->slug,
                'source_type' => $chunk->document?->source_type ?? ($chunk->faq_id ? 'faq' : null),
                'content' => $chunk->content,
            ]);

            $scored[] = [
                'point_id' => (string) ($chunk->vector_point_id ?? $chunk->id),
                'score' => $score,
                'payload' => $payload,
            ];
        }

        usort($scored, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, max(1, $limit));
    }

    /**
     * @param  list<string>  $pointIds
     */
    public function deleteByIds(array $pointIds): void
    {
        $ids = array_values(array_filter(array_map('strval', $pointIds), static fn (string $v) => $v !== ''));
        if ($ids === []) {
            return;
        }

        DB::transaction(function () use ($ids): void {
            $chunkIds = KnowledgeChunk::query()
                ->whereIn('vector_point_id', $ids)
                ->pluck('id')
                ->all();

            if ($chunkIds !== []) {
                KnowledgeEmbedding::query()->whereIn('chunk_id', $chunkIds)->delete();
                KnowledgeChunk::query()->whereIn('id', $chunkIds)->update(['vector_point_id' => null]);
            }
        });
    }

    public function driverName(): string
    {
        return 'mysql';
    }

    /**
     * @param  list<float>  $a
     * @param  list<float|int>  $b
     */
    private function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = count($a);

        for ($i = 0; $i < $length; $i++) {
            $va = (float) $a[$i];
            $vb = (float) $b[$i];
            $dot += $va * $vb;
            $normA += $va * $va;
            $normB += $vb * $vb;
        }

        $denominator = sqrt($normA) * sqrt($normB);
        if ($denominator <= 0.0) {
            return 0.0;
        }

        return round($dot / $denominator, 6);
    }
}
