<?php

namespace App\Services\AI;

use App\Contracts\EmbeddingProviderInterface;
use App\Exceptions\EmbeddingProviderException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleEmbeddingProvider implements EmbeddingProviderInterface
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
        $this->apiKey = trim((string) ($apiKey ?? config('ai.providers.openai_compatible.api_key', '')));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('ai.providers.openai_compatible.base_url', 'https://api.openai.com/v1')), '/');
        $this->model = trim((string) ($model ?? config('ai.providers.openai_compatible.embedding_model', 'text-embedding-3-small')));
        $this->dimensions = (int) ($dimensions ?? config('ai.providers.openai_compatible.embedding_dimensions', config('ai.embedding.dimensions', 768)));
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

        if ($this->apiKey === '') {
            throw new EmbeddingProviderException(
                'OPENAI_API_KEY belum dikonfigurasi di server (.env).'
            );
        }

        $endpoint = "{$this->baseUrl}/embeddings";

        try {
            $response = Http::timeout($this->timeout)
                ->withToken($this->apiKey)
                ->acceptJson()
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
                ->post($endpoint, [
                    'model' => $this->model,
                    'input' => $cleanText,
                    'dimensions' => $this->dimensions,
                ]);
        } catch (ConnectionException $e) {
            throw new EmbeddingProviderException(
                'Koneksi ke OpenAI-Compatible Embedding API timeout atau gagal dijangkau.',
                0,
                $e
            );
        }

        if ($response->status() === 429) {
            throw new EmbeddingProviderException('Rate limit OpenAI-Compatible Embedding API tercapai.');
        }

        if (! $response->successful()) {
            $apiMessage = (string) ($response->json('error.message') ?? 'Kesalahan tidak diketahui.');
            $safeMessage = str_replace($this->apiKey, '[REDACTED]', $apiMessage);

            throw new EmbeddingProviderException(
                "OpenAI-Compatible Embedding API gagal (HTTP {$response->status()}): {$safeMessage}"
            );
        }

        $values = $response->json('data.0.embedding');
        if (! is_array($values) || count($values) !== $this->dimensions) {
            throw new EmbeddingProviderException(
                'Respons OpenAI-Compatible Embedding API tidak memiliki dimensi vektor yang sesuai.'
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
