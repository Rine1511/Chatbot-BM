<?php

namespace App\Services\AI;

use App\Contracts\EmbeddingProviderInterface;
use App\Exceptions\EmbeddingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeminiEmbeddingProvider implements EmbeddingProviderInterface
{
    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly string $model;

    private readonly int $dimensions;

    private readonly int $timeout;

    private readonly int $retryTimes;

    private readonly int $retrySleepMs;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null,
        ?int $dimensions = null,
        ?int $timeout = null,
        ?int $retryTimes = null,
        ?int $retrySleepMs = null,
    ) {
        $this->apiKey = trim((string) ($apiKey ?? config('ai.providers.gemini.api_key', '')));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta')), '/');
        $this->model = trim((string) ($model ?? config('ai.embedding.model', 'gemini-embedding-001')));
        $this->dimensions = (int) ($dimensions ?? config('ai.embedding.dimensions', 768));
        $this->timeout = max(1, (int) ($timeout ?? config('ai.embedding.timeout', 20)));
        $this->retryTimes = max(0, (int) ($retryTimes ?? config('ai.embedding.retry_times', 2)));
        $this->retrySleepMs = max(0, (int) ($retrySleepMs ?? config('ai.embedding.retry_sleep_ms', 500)));
    }

    /**
     * @return list<float>
     */
    public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array
    {
        $cleanText = trim($text);
        if ($cleanText === '') {
            throw new EmbeddingProviderException('Teks untuk embedding tidak boleh kosong.');
        }

        if ($this->apiKey === '' || in_array($this->apiKey, ['MY_GEMINI_API_KEY', 'ISI_API_KEY_GEMINI_ANDA'], true)) {
            throw new EmbeddingProviderException(
                'GEMINI_API_KEY belum dikonfigurasi di server (.env). Proses embedding dibatalkan.'
            );
        }

        $modelResource = str_starts_with($this->model, 'models/')
            ? $this->model
            : 'models/'.$this->model;

        $endpoint = "{$this->baseUrl}/{$modelResource}:embedContent";

        $payload = [
            'model' => $modelResource,
            'content' => [
                'parts' => [
                    ['text' => $cleanText],
                ],
            ],
            'taskType' => $taskType,
            'outputDimensionality' => $this->dimensions,
        ];

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-goog-api-key' => $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->retry(
                    $this->retryTimes + 1,
                    $this->retrySleepMs,
                    function ($exception): bool {
                        if ($exception instanceof ConnectionException) {
                            return true;
                        }

                        if ($exception instanceof RequestException && $exception->response) {
                            $status = $exception->response->status();

                            return $status === 429 || $status >= 500;
                        }

                        return false;
                    },
                    throw: false
                )
                ->post($endpoint, $payload);
        } catch (ConnectionException $e) {
            throw new EmbeddingProviderException(
                'Koneksi ke Gemini Embedding API timeout atau gagal dijangkau.',
                0,
                $e
            );
        }

        if ($response->status() === 429) {
            throw new EmbeddingProviderException(
                'Batas kuota (rate limit) Gemini Embedding API tercapai. Silakan coba beberapa saat lagi.'
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new EmbeddingProviderException(
                'Autentikasi ke Gemini Embedding API ditolak. Periksa validitas konfigurasi GEMINI_API_KEY di server.'
            );
        }

        if (! $response->successful()) {
            $apiMessage = (string) ($response->json('error.message') ?? 'Kesalahan tidak diketahui.');
            // Pastikan tidak pernah menyisipkan API key dalam pesan error
            $safeMessage = str_replace($this->apiKey, '[REDACTED]', $apiMessage);

            throw new EmbeddingProviderException(
                "Gemini Embedding API gagal (HTTP {$response->status()}): {$safeMessage}"
            );
        }

        $values = $response->json('embedding.values');
        if (! is_array($values) || $values === []) {
            throw new EmbeddingProviderException(
                'Respons Gemini Embedding API tidak menyertakan vektor embedding yang valid.'
            );
        }

        if (count($values) !== $this->dimensions) {
            throw new EmbeddingProviderException(
                sprintf(
                    'Dimensi vektor dari %s (%d) tidak sesuai dengan konfigurasi sistem (%d).',
                    $this->model,
                    count($values),
                    $this->dimensions
                )
            );
        }

        return array_values(array_map(static fn ($v) => (float) $v, $values));
    }

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function modelName(): string
    {
        return $this->model;
    }
}
