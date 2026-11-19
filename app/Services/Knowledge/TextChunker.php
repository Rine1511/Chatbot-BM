<?php

namespace App\Services\Knowledge;

use InvalidArgumentException;

class TextChunker
{
    public function __construct(
        private readonly TextNormalizer $normalizer,
    ) {}

    /**
     * Bagi teks menjadi potongan (chunks) berurutan dengan overlap yang dapat dikonfigurasi.
     *
     * @return list<array{
     *     chunk_index: int,
     *     content: string,
     *     token_count: int,
     *     content_hash: string,
     *     char_length: int
     * }>
     */
    public function chunk(string $text, ?int $chunkSize = null, ?int $chunkOverlap = null): array
    {
        $size = $chunkSize ?? (int) config('ai.rag.chunk_size', 800);
        $overlap = $chunkOverlap ?? (int) config('ai.rag.chunk_overlap', 120);

        if ($size < 50) {
            throw new InvalidArgumentException('Ukuran chunk minimal 50 karakter.');
        }

        if ($overlap < 0 || $overlap >= $size) {
            throw new InvalidArgumentException('Overlap chunk harus bernilai >= 0 dan lebih kecil dari ukuran chunk.');
        }

        $cleanText = $this->normalizer->normalize($text);
        if ($cleanText === '') {
            return [];
        }

        $totalLength = mb_strlen($cleanText);
        if ($totalLength <= $size) {
            return [$this->makeChunkPayload(0, $cleanText)];
        }

        $chunks = [];
        $offset = 0;
        $index = 0;

        while ($offset < $totalLength) {
            $remaining = $totalLength - $offset;

            if ($remaining <= $size) {
                $slice = trim(mb_substr($cleanText, $offset));
                if ($slice !== '') {
                    $chunks[] = $this->makeChunkPayload($index, $slice);
                }
                break;
            }

            $window = mb_substr($cleanText, $offset, $size);
            $splitLength = $this->findPreferredSplitLength($window, $size);

            $chunkContent = trim(mb_substr($cleanText, $offset, $splitLength));
            if ($chunkContent !== '') {
                $chunks[] = $this->makeChunkPayload($index, $chunkContent);
                $index++;
            }

            $actualAdvance = max(1, $splitLength - $overlap);
            $nextOffset = $offset + $actualAdvance;

            // Geser ke awal kata bila mundur karena overlap memotong di tengah kata
            if ($overlap > 0 && $nextOffset < $totalLength && $nextOffset > $offset) {
                $nextOffset = $this->alignToWordStart($cleanText, $nextOffset, $offset + $splitLength);
            }

            if ($nextOffset <= $offset) {
                $nextOffset = $offset + max(1, $splitLength);
            }

            $offset = $nextOffset;
        }

        return $chunks;
    }

    private function findPreferredSplitLength(string $window, int $targetSize): int
    {
        $minBoundary = (int) floor($targetSize * 0.5);

        // 1. Prioritaskan batas paragraf
        $paragraphPos = mb_strrpos($window, "\n\n");
        if ($paragraphPos !== false && $paragraphPos >= $minBoundary) {
            return $paragraphPos;
        }

        // 2. Prioritaskan batas baris baru
        $linePos = mb_strrpos($window, "\n");
        if ($linePos !== false && $linePos >= $minBoundary) {
            return $linePos;
        }

        // 3. Prioritaskan akhir kalimat
        foreach (['. ', '! ', '? '] as $sentenceEnd) {
            $pos = mb_strrpos($window, $sentenceEnd);
            if ($pos !== false && $pos >= $minBoundary) {
                return $pos + 1;
            }
        }

        // 4. Prioritaskan spasi antar kata
        $spacePos = mb_strrpos($window, ' ');
        if ($spacePos !== false && $spacePos >= (int) floor($targetSize * 0.35)) {
            return $spacePos;
        }

        return $targetSize;
    }

    private function alignToWordStart(string $fullText, int $candidateOffset, int $hardLimit): int
    {
        if ($candidateOffset <= 0) {
            return 0;
        }

        $prevChar = mb_substr($fullText, $candidateOffset - 1, 1);
        $currChar = mb_substr($fullText, $candidateOffset, 1);

        if (preg_match('/\s/u', $prevChar) === 1 || preg_match('/\s/u', $currChar) === 1) {
            while ($candidateOffset < $hardLimit && preg_match('/\s/u', mb_substr($fullText, $candidateOffset, 1)) === 1) {
                $candidateOffset++;
            }

            return $candidateOffset;
        }

        $tail = mb_substr($fullText, $candidateOffset, min(40, max(0, $hardLimit - $candidateOffset)));
        $nextSpace = mb_strpos($tail, ' ');
        if ($nextSpace !== false) {
            return $candidateOffset + $nextSpace + 1;
        }

        return $candidateOffset;
    }

    /**
     * @return array{
     *     chunk_index: int,
     *     content: string,
     *     token_count: int,
     *     content_hash: string,
     *     char_length: int
     * }
     */
    private function makeChunkPayload(int $index, string $content): array
    {
        return [
            'chunk_index' => $index,
            'content' => $content,
            'token_count' => $this->normalizer->estimateTokens($content),
            'content_hash' => hash('sha256', $content),
            'char_length' => mb_strlen($content),
        ];
    }
}
