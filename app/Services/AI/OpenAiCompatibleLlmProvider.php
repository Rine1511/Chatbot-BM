<?php

namespace App\Services\AI;

use App\Contracts\LlmProviderInterface;
use App\Exceptions\LlmProviderException;
use App\Support\SecretRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class OpenAiCompatibleLlmProvider implements LlmProviderInterface
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
        $this->apiKey = trim((string) ($apiKey ?? config('ai.providers.openai_compatible.api_key', '')));
        $this->baseUrl = rtrim((string) ($baseUrl ?? config('ai.providers.openai_compatible.base_url', 'https://api.openai.com/v1')), '/');
        $this->model = trim((string) ($model ?? config('ai.providers.openai_compatible.llm_model', 'gpt-4o-mini')));
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
        if ($this->apiKey === '') {
            throw new LlmProviderException(
                'OPENAI_API_KEY belum dikonfigurasi untuk driver LLM openai_compatible.'
            );
        }

        $chatMessages = [];
        $cleanSystem = trim($systemPrompt);
        if ($cleanSystem !== '') {
            $chatMessages[] = ['role' => 'system', 'content' => $cleanSystem];
        }

        foreach ($messages as $message) {
            $content = trim((string) ($message['content'] ?? ''));
            if ($content === '') {
                continue;
            }

            $rawRole = strtolower(trim((string) ($message['role'] ?? 'user')));
            $role = in_array($rawRole, ['assistant', 'model'], true) ? 'assistant' : 'user';
            $chatMessages[] = ['role' => $role, 'content' => $content];
        }

        if ($chatMessages === [] || ($cleanSystem !== '' && count($chatMessages) === 1)) {
            throw new LlmProviderException('Daftar pesan percakapan untuk LLM tidak boleh kosong.');
        }

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
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => (string) ($options['model'] ?? $this->model),
                    'messages' => $chatMessages,
                    'temperature' => (float) ($options['temperature'] ?? $this->temperature),
                    'max_tokens' => max(1, (int) ($options['max_tokens'] ?? $this->maxTokens)),
                ]);
        } catch (ConnectionException $e) {
            throw new LlmProviderException(
                'Koneksi ke OpenAI-compatible LLM API timeout atau gagal dijangkau.',
                0,
                $e
            );
        }

        if (! $response->successful()) {
            $apiMessage = (string) ($response->json('error.message') ?? 'Kesalahan tidak diketahui.');
            $safeMessage = SecretRedactor::redact(str_replace($this->apiKey, '[REDACTED]', $apiMessage));

            throw new LlmProviderException(
                "OpenAI-compatible LLM API gagal (HTTP {$response->status()}): {$safeMessage}"
            );
        }

        $reply = trim((string) ($response->json('choices.0.message.content') ?? ''));
        if ($reply === '') {
            throw new LlmProviderException(
                'OpenAI-compatible LLM API mengembalikan respons teks kosong.'
            );
        }

        return $reply;
    }

    public function driverName(): string
    {
        return 'openai_compatible';
    }
}
