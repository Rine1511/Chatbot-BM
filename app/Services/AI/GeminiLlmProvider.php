<?php

namespace App\Services\AI;

use App\Contracts\LlmProviderInterface;
use App\Exceptions\LlmProviderException;
use App\Support\SecretRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class GeminiLlmProvider implements LlmProviderInterface
{
    private readonly string $apiKey;

    private readonly string $baseUrl;

    private readonly string $model;

    private readonly float $temperature;

    private readonly int $maxTokens;

    private readonly int $timeout;

    private readonly int $retryTimes;

    private readonly int $retrySleepMs;

    public function __construct(
        ?string $apiKey = null,
        ?string $baseUrl = null,
        ?string $model = null,
        ?float $temperature = null,
        ?int $maxTokens = null,
        ?int $timeout = null,
        ?int $retryTimes = null,
        ?int $retrySleepMs = null,
    ) {
        $this->apiKey = trim((string) ($apiKey ?? config('ai.providers.gemini.api_key', '')));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('ai.providers.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta')), '/');
        $this->model = trim((string) ($model ?? config('ai.llm.model', 'gemini-2.5-flash')));
        $this->temperature = (float) ($temperature ?? config('ai.llm.temperature', 0.2));
        $this->maxTokens = max(1, (int) ($maxTokens ?? config('ai.llm.max_tokens', 1024)));
        $this->timeout = max(1, (int) ($timeout ?? config('ai.llm.timeout', 30)));
        $this->retryTimes = max(0, (int) ($retryTimes ?? config('ai.llm.retry_times', 2)));
        $this->retrySleepMs = max(0, (int) ($retrySleepMs ?? config('ai.llm.retry_sleep_ms', 500)));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     */
    public function generateReply(string $systemPrompt, array $messages, array $options = []): string
    {
        if ($this->apiKey === '' || in_array($this->apiKey, ['MY_GEMINI_API_KEY', 'ISI_API_KEY_GEMINI_ANDA'], true)) {
            throw new LlmProviderException(
                'GEMINI_API_KEY belum dikonfigurasi di server (.env). Proses generasi jawaban dibatalkan.'
            );
        }

        $contents = [];
        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $rawRole = strtolower(trim((string) ($message['role'] ?? 'user')));
            $geminiRole = in_array($rawRole, ['assistant', 'model'], true) ? 'model' : 'user';

            $contents[] = [
                'role' => $geminiRole,
                'parts' => [
                    ['text' => $content],
                ],
            ];
        }

        if ($contents === []) {
            throw new LlmProviderException('Daftar pesan percakapan untuk LLM tidak boleh kosong.');
        }

        $modelName = trim((string) ($options['model'] ?? $this->model));
        $modelResource = str_starts_with($modelName, 'models/')
            ? $modelName
            : 'models/'.$modelName;

        $endpoint = "{$this->baseUrl}/{$modelResource}:generateContent";

        $payload = [
            'contents' => $contents,
            'generationConfig' => [
                'temperature' => (float) ($options['temperature'] ?? $this->temperature),
                'maxOutputTokens' => max(1, (int) ($options['max_tokens'] ?? $this->maxTokens)),
            ],
        ];

        $cleanSystemPrompt = trim($systemPrompt);
        if ($cleanSystemPrompt !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $cleanSystemPrompt],
                ],
            ];
        }

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
            throw new LlmProviderException(
                'Koneksi ke Gemini LLM API timeout atau tidak dapat dijangkau.',
                0,
                $e
            );
        }

        if ($response->status() === 429) {
            throw new LlmProviderException(
                'Batas kuota (rate limit) Gemini LLM API tercapai. Silakan coba beberapa saat lagi.'
            );
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new LlmProviderException(
                'Autentikasi ke Gemini LLM API ditolak. Periksa validitas konfigurasi GEMINI_API_KEY di server.'
            );
        }

        if (! $response->successful()) {
            $apiMessage = (string) ($response->json('error.message') ?? 'Kesalahan tidak diketahui.');
            $safeMessage = SecretRedactor::redact(str_replace($this->apiKey, '[REDACTED]', $apiMessage));

            throw new LlmProviderException(
                "Gemini LLM API gagal (HTTP {$response->status()}): {$safeMessage}"
            );
        }

        $parts = $response->json('candidates.0.content.parts');
        if (! is_array($parts)) {
            $blockReason = (string) ($response->json('promptFeedback.blockReason') ?? '');
            if ($blockReason !== '') {
                throw new LlmProviderException(
                    "Permintaan ditolak oleh filter keamanan Gemini ({$blockReason})."
                );
            }

            throw new LlmProviderException(
                'Respons Gemini LLM API tidak menyertakan kandidat jawaban yang valid.'
            );
        }

        $textChunks = [];
        foreach ($parts as $part) {
            if (is_array($part) && isset($part['text']) && is_string($part['text'])) {
                $textChunks[] = $part['text'];
            }
        }

        $reply = trim(implode("\n", $textChunks));
        if ($reply === '') {
            throw new LlmProviderException(
                'Gemini LLM API mengembalikan jawaban teks kosong.'
            );
        }

        return $reply;
    }

    public function driverName(): string
    {
        return 'gemini';
    }
}
