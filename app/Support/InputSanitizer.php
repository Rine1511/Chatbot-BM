<?php

namespace App\Support;

class InputSanitizer
{
    public static function cleanSingleLine(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stripped = strip_tags($value);
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $stripped) ?? $stripped;
        $trimmed = trim(preg_replace('/\s+/u', ' ', $normalized) ?? $normalized);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function cleanMultiLine(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stripped = strip_tags($value);
        $normalized = str_replace(["\r\n", "\r"], "\n", $stripped);
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $normalized) ?? $normalized;
        $trimmed = trim($cleaned);

        return $trimmed === '' ? null : $trimmed;
    }

    public static function cleanUrl(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim(strip_tags($value));
        if ($trimmed === '') {
            return null;
        }

        $scheme = strtolower((string) parse_url($trimmed, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return $trimmed;
    }
}
