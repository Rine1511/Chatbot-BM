<?php

namespace App\Services\AI;

use App\Contracts\EmbeddingProviderInterface;
use InvalidArgumentException;

class EmbeddingProviderManager
{
    public function driver(?string $name = null): EmbeddingProviderInterface
    {
        $driver = $name ?? (string) config('ai.embedding.driver', 'gemini');

        return match ($driver) {
            'gemini' => app(GeminiEmbeddingProvider::class),
            'openai_compatible' => app(OpenAiCompatibleEmbeddingProvider::class),
            default => throw new InvalidArgumentException("Driver embedding [{$driver}] tidak didukung."),
        };
    }
}
