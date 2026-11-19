<?php

namespace App\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * Hasilkan vektor embedding numerik untuk sebuah teks.
     *
     * @return list<float>
     */
    public function embed(string $text, string $taskType = 'RETRIEVAL_DOCUMENT'): array;

    /**
     * Dimensi vektor yang dihasilkan oleh model aktif.
     */
    public function dimensions(): int;

    /**
     * Nama model embedding aktif.
     */
    public function modelName(): string;
}
