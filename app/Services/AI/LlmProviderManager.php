<?php

namespace App\Services\AI;

use App\Contracts\LlmProviderInterface;
use InvalidArgumentException;

class LlmProviderManager
{
    public function driver(?string $name = null): LlmProviderInterface
    {
        $driver = $name ?? (string) config('ai.llm.driver', 'gemini');

        return match ($driver) {
            'gemini' => app(GeminiLlmProvider::class),
            'openai_compatible' => app(OpenAiCompatibleLlmProvider::class),
            default => throw new InvalidArgumentException("Driver LLM [{$driver}] tidak didukung."),
        };
    }
}
