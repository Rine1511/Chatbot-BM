<?php

namespace App\Support;

class SecretRedactor
{
    public static function redact(string $text): string
    {
        if ($text === '') {
            return '';
        }

        $secrets = array_filter([
            (string) config('ai.providers.gemini.api_key', ''),
            (string) config('ai.providers.openai_compatible.api_key', ''),
            (string) config('ai.vector.qdrant.api_key', ''),
            (string) config('sibbm.api_key', ''),
        ], static fn (string $val): bool => trim($val) !== '' && ! in_array(trim($val), ['MY_GEMINI_API_KEY', 'ISI_API_KEY_GEMINI_ANDA'], true));

        foreach ($secrets as $secret) {
            $text = str_replace(trim($secret), '[REDACTED]', $text);
        }

        return $text;
    }
}
