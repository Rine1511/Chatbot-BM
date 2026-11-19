<?php

namespace App\Contracts;

interface VectorStoreInterface
{
    /**
     * Periksa apakah layanan vector storage aktif dapat dijangkau.
     */
    public function isAvailable(): bool;

    /**
     * Pastikan koleksi / penyimpanan vektor siap digunakan sesuai dimensi embedding.
     */
    public function ensureCollection(int $dimensions): void;

    /**
     * Simpan atau perbarui titik vektor beserta metadata chunk terstandar.
     *
     * Payload minimal yang didukung:
     * - document_id (int|null)
     * - faq_id (int|null)
     * - chunk_id (int)
     * - category_id (int)
     * - category_slug (string)
     * - source_type (string: 'faq' | 'text' | 'file' | 'sib_bm_sync')
     *
     * @param  list<float>  $vector
     * @param  array{
     *     document_id?: int|null,
     *     faq_id?: int|null,
     *     chunk_id: int,
     *     category_id: int,
     *     category_slug: string,
     *     source_type?: string
     * }&array<string, mixed>  $payload
     */
    public function upsert(string $pointId, array $vector, array $payload): void;

    /**
     * Cari top-K chunk paling mirip berdasarkan vektor query dan filter metadata opsional.
     *
     * @param  list<float>  $queryVector
     * @param  array{category_id?: int, category_slug?: string, document_id?: int, source_type?: string}&array<string, mixed>  $filters
     * @return array<int, array{point_id: string, score: float, payload: array<string, mixed>}>
     */
    public function search(array $queryVector, int $limit = 5, array $filters = []): array;

    /**
     * Hapus titik vektor berdasarkan daftar point ID.
     *
     * @param  list<string>  $pointIds
     */
    public function deleteByIds(array $pointIds): void;

    /**
     * Nama driver vector storage aktif (mis. 'qdrant', 'mysql').
     */
    public function driverName(): string;
}
