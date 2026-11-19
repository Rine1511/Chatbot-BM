<?php

namespace App\Services\Knowledge;

class TextNormalizer
{
    /**
     * Normalisasi teks hasil ekstraksi tanpa menghilangkan informasi penting.
     */
    public function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        // Pastikan encoding UTF-8 valid
        if (! mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            $text = is_string($converted) ? $converted : '';
        }

        // Hapus BOM UTF-8 dan karakter kontrol berbahaya (kecuali \t dan \n)
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[\x01-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        // Samakan line ending ke LF
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Rapikan spasi horizontal berlebih per baris tanpa menghancurkan struktur paragraf
        $lines = explode("\n", $text);
        $normalizedLines = array_map(static function (string $line): string {
            $line = preg_replace('/[ \t\x{00A0}]+/u', ' ', $line) ?? $line;

            return trim($line);
        }, $lines);

        $joined = implode("\n", $normalizedLines);

        // Batasi baris kosong berturut-turut menjadi maksimal satu baris pemisah paragraf
        $joined = preg_replace("/\n{3,}/", "\n\n", $joined) ?? $joined;

        return trim($joined);
    }

    public function estimateTokens(string $text): int
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return 0;
        }

        $charEstimate = (int) ceil(mb_strlen($trimmed) / 4);
        $words = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $wordEstimate = (int) ceil(count($words) * 1.3);

        return max(1, $charEstimate, $wordEstimate);
    }
}
