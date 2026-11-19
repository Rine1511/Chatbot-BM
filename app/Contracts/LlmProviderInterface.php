<?php

namespace App\Contracts;

interface LlmProviderInterface
{
    /**
     * Hasilkan jawaban teks dari LLM berdasarkan system prompt dan konteks percakapan.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     */
    public function generateReply(string $systemPrompt, array $messages, array $options = []): string;

    /**
     * Nama driver aktif (mis. 'gemini', 'openai_compatible').
     */
    public function driverName(): string;
}
