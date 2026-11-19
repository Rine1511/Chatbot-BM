<?php

namespace App\Services\VectorStore;

use App\Contracts\VectorStoreInterface;
use App\Exceptions\VectorStoreException;
use App\Support\SecretRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class QdrantVectorStore implements VectorStoreInterface
{
    private readonly string $host;

    private readonly string $apiKey;

    private readonly string $collection;

    private readonly int $vectorSize;

    private readonly string $distance;

    private readonly int $timeout;

    public function __construct(
        ?string $host = null,
        ?string $apiKey = null,
        ?string $collection = null,
        ?int $vectorSize = null,
        ?string $distance = null,
        ?int $timeout = null,
    ) {
        $this->host = rtrim((string) ($host ?? config('ai.vector.qdrant.host', 'http://127.0.0.1:6333')), '/');
        $this->apiKey = trim((string) ($apiKey ?? config('ai.vector.qdrant.api_key', '')));
        $this->collection = trim((string) ($collection ?? config('ai.vector.qdrant.collection', 'bm_knowledge_chunks')));
        $this->vectorSize = (int) ($vectorSize ?? config('ai.vector.qdrant.vector_size', 768));
        $this->distance = (string) ($distance ?? config('ai.vector.qdrant.distance', 'Cosine'));
        $this->timeout = max(1, (int) ($timeout ?? config('ai.vector.qdrant.timeout', 10)));
    }

    public function isAvailable(): bool
    {
        if ($this->host === '') {
            return false;
        }

        try {
            $response = $this->client(min(3, $this->timeout))->get("{$this->host}/collections");

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function ensureCollection(int $dimensions): void
    {
        if ($dimensions <= 0) {
            throw new VectorStoreException('Dimensi koleksi Qdrant harus lebih besar dari 0.');
        }

        try {
            $checkResponse = $this->client()->get("{$this->host}/collections/{$this->collection}");
        } catch (ConnectionException $e) {
            throw new VectorStoreException('Qdrant tidak dapat dijangkau saat memeriksa koleksi.', 0, $e);
        }

        if ($checkResponse->successful()) {
            $existingSize = (int) ($checkResponse->json('result.config.params.vectors.size') ?? 0);
            if ($existingSize > 0 && $existingSize !== $dimensions) {
                throw new VectorStoreException(
                    sprintf(
                        'Dimensi koleksi Qdrant "%s" (%d) tidak cocok dengan dimensi embedding (%d).',
                        $this->collection,
                        $existingSize,
                        $dimensions
                    )
                );
            }

            return;
        }

        if ($checkResponse->status() !== 404) {
            throw new VectorStoreException(
                "Gagal memeriksa koleksi Qdrant (HTTP {$checkResponse->status()})."
            );
        }

        try {
            $createResponse = $this->client()->put("{$this->host}/collections/{$this->collection}", [
                'vectors' => [
                    'size' => $dimensions,
                    'distance' => $this->distance,
                ],
            ]);
        } catch (ConnectionException $e) {
            throw new VectorStoreException('Qdrant timeout saat membuat koleksi.', 0, $e);
        }

        if (! $createResponse->successful()) {
            throw new VectorStoreException(
                "Gagal membuat koleksi Qdrant \"{$this->collection}\" (HTTP {$createResponse->status()})."
            );
        }
    }

    /**
     * @param  list<float>  $vector
     * @param  array<string, mixed>  $payload
     */
    public function upsert(string $pointId, array $vector, array $payload): void
    {
        $this->upsertBatch([
            [
                'id' => $pointId,
                'vector' => $vector,
                'payload' => $payload,
            ],
        ]);
    }

    /**
     * Simpan atau perbarui banyak titik vektor dalam satu batch Qdrant yang atomik.
     *
     * @param  list<array{id: string, vector: list<float>, payload: array<string, mixed>}>  $points
     */
    public function upsertBatch(array $points): void
    {
        if ($points === []) {
            return;
        }

        $formattedPoints = [];
        foreach ($points as $point) {
            if (count($point['vector']) !== $this->vectorSize) {
                throw new VectorStoreException(
                    sprintf('Dimensi vektor (%d) tidak sesuai dengan konfigurasi Qdrant (%d).', count($point['vector']), $this->vectorSize)
                );
            }

            $payload = $point['payload'];
            if (! array_key_exists('is_active', $payload)) {
                $payload['is_active'] = true;
            }

            $formattedPoints[] = [
                'id' => $point['id'],
                'vector' => array_values(array_map('floatval', $point['vector'])),
                'payload' => $payload,
            ];
        }

        try {
            $response = $this->withRetry($this->client())
                ->put("{$this->host}/collections/{$this->collection}/points?wait=true", [
                    'points' => $formattedPoints,
                ]);
        } catch (ConnectionException $e) {
            throw new VectorStoreException('Koneksi ke Qdrant gagal saat menyimpan titik vektor.', 0, $e);
        }

        if (! $response->successful()) {
            $detail = SecretRedactor::redact((string) ($response->json('status.error') ?? ''));
            $suffix = $detail !== '' ? ": {$detail}" : '';
            throw new VectorStoreException(
                "Gagal menyimpan titik vektor ke Qdrant (HTTP {$response->status()}){$suffix}."
            );
        }
    }

    /**
     * @param  list<float>  $queryVector
     * @param  array<string, mixed>  $filters
     * @return array<int, array{point_id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(array $queryVector, int $limit = 5, array $filters = []): array
    {
        if (count($queryVector) !== $this->vectorSize) {
            throw new VectorStoreException(
                sprintf('Dimensi vektor query (%d) tidak sesuai dengan koleksi Qdrant (%d).', count($queryVector), $this->vectorSize)
            );
        }

        $body = [
            'vector' => array_values(array_map('floatval', $queryVector)),
            'limit' => max(1, $limit),
            'with_payload' => true,
            'filter' => $this->buildMustFilter($filters),
        ];

        try {
            $response = $this->withRetry($this->client())
                ->post("{$this->host}/collections/{$this->collection}/points/search", $body);
        } catch (ConnectionException $e) {
            throw new VectorStoreException('Koneksi ke Qdrant gagal saat melakukan pencarian vektor.', 0, $e);
        }

        if (! $response->successful()) {
            throw new VectorStoreException(
                "Pencarian vektor Qdrant gagal (HTTP {$response->status()})."
            );
        }

        $results = $response->json('result');
        if (! is_array($results)) {
            return [];
        }

        $mapped = [];
        foreach ($results as $item) {
            if (! is_array($item) || ! isset($item['id'])) {
                continue;
            }

            $mapped[] = [
                'point_id' => (string) $item['id'],
                'score' => (float) ($item['score'] ?? 0.0),
                'payload' => is_array($item['payload'] ?? null) ? $item['payload'] : [],
            ];
        }

        return $mapped;
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

        try {
            $response = $this->withRetry($this->client())
                ->post("{$this->host}/collections/{$this->collection}/points/delete?wait=true", [
                    'points' => $ids,
                ]);
        } catch (ConnectionException $e) {
            throw new VectorStoreException('Koneksi ke Qdrant gagal saat menghapus titik vektor.', 0, $e);
        }

        if (! $response->successful()) {
            throw new VectorStoreException(
                "Gagal menghapus titik vektor dari Qdrant (HTTP {$response->status()})."
            );
        }
    }

    public function driverName(): string
    {
        return 'qdrant';
    }

    private function client(?int $timeoutOverride = null): PendingRequest
    {
        $request = Http::timeout($timeoutOverride ?? $this->timeout)->acceptJson();

        if ($this->apiKey !== '') {
            $request = $request->withHeaders(['api-key' => $this->apiKey]);
        }

        return $request;
    }

    private function withRetry(PendingRequest $request): PendingRequest
    {
        return $request->retry(
            2,
            250,
            function ($exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if ($exception instanceof RequestException && $exception->response) {
                    return $exception->response->status() >= 500;
                }

                return false;
            },
            throw: false
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{must: list<array<string, mixed>>}
     */
    private function buildMustFilter(array $filters): array
    {
        $must = [
            [
                'key' => 'is_active',
                'match' => ['value' => true],
            ],
        ];

        foreach (['category_id', 'document_id', 'faq_id'] as $intKey) {
            if (isset($filters[$intKey]) && (int) $filters[$intKey] > 0) {
                $must[] = [
                    'key' => $intKey,
                    'match' => ['value' => (int) $filters[$intKey]],
                ];
            }
        }

        foreach (['category_slug', 'source_type'] as $strKey) {
            if (isset($filters[$strKey]) && trim((string) $filters[$strKey]) !== '') {
                $must[] = [
                    'key' => $strKey,
                    'match' => ['value' => trim((string) $filters[$strKey])],
                ];
            }
        }

        return ['must' => $must];
    }
}
