<?php

namespace App\Services\SibBm;

use App\Exceptions\SibBmException;
use App\Models\FaqCategory;
use App\Models\KnowledgeDocument;
use App\Models\SibBmBuildingCache;
use App\Services\Knowledge\KnowledgeIndexingService;
use App\Services\Knowledge\TextNormalizer;
use App\Support\SecretRedactor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class SibBmSyncService
{
    public function __construct(
        private readonly SibBmClient $client,
        private readonly TextNormalizer $normalizer,
        private readonly KnowledgeIndexingService $indexingService,
    ) {}

    /**
     * Sinkronisasi dari sumber SIB-BM ('auto' | 'api' | 'local') ke cache & Knowledge Base.
     *
     * @return array{
     *     source: string,
     *     total: int,
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     indexed: int,
     *     index_deferred: int,
     *     deactivated: int
     * }
     */
    public function syncFromSource(
        string $source = 'auto',
        ?int $actorId = null,
        bool $autoIndex = true,
        bool $pruneMissing = false,
        ?string $localPath = null,
    ): array {
        $fetched = $this->client->fetchBuildings($source, $localPath);

        if ($fetched['source'] === 'unconfigured') {
            return [
                'source' => 'unconfigured',
                'total' => 0,
                'created' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'indexed' => 0,
                'index_deferred' => 0,
                'deactivated' => 0,
            ];
        }

        $stats = $this->syncBuildings($fetched['buildings'], $actorId, $autoIndex, $pruneMissing);
        $stats['source'] = $fetched['source'];

        return $stats;
    }

    /**
     * Sinkronisasi daftar data bangunan SIB-BM ke `sib_bm_buildings_cache` dan `knowledge_documents` tanpa duplikasi.
     *
     * @param  list<array<string, mixed>>  $rawBuildings
     * @return array{
     *     source: string,
     *     total: int,
     *     created: int,
     *     updated: int,
     *     unchanged: int,
     *     indexed: int,
     *     index_deferred: int,
     *     deactivated: int
     * }
     */
    public function syncBuildings(
        array $rawBuildings,
        ?int $actorId = null,
        bool $autoIndex = true,
        bool $pruneMissing = false,
    ): array {
        $buildings = $this->client->normalizePayload($rawBuildings);

        $buildingCategory = FaqCategory::query()->where('slug', 'informasi-bangunan')->first();
        if (! $buildingCategory) {
            $buildingCategory = FaqCategory::query()->firstOrCreate(
                ['slug' => 'informasi-bangunan'],
                [
                    'name' => 'Informasi Bangunan',
                    'description' => 'Informasi bangunan, lokasi, fungsi, kapasitas, dan spesifikasi dari SIB-BM.',
                    'sort_order' => 6,
                    'is_active' => true,
                ]
            );
        }

        $stats = [
            'source' => 'payload',
            'total' => count($buildings),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'indexed' => 0,
            'index_deferred' => 0,
            'deactivated' => 0,
        ];

        $seenSourceLabels = [];
        $docsToIndex = [];

        DB::transaction(function () use (
            $buildings,
            $buildingCategory,
            $actorId,
            $pruneMissing,
            &$stats,
            &$seenSourceLabels,
            &$docsToIndex,
        ): void {
            $now = now();

            foreach ($buildings as $building) {
                $externalId = (string) $building['external_id'];
                $sourceLabel = "sibbm:{$externalId}";
                $seenSourceLabels[] = $sourceLabel;

                SibBmBuildingCache::query()->updateOrCreate(
                    ['external_id' => $externalId],
                    [
                        'name' => $building['name'],
                        'category' => $building['category'],
                        'function_desc' => $building['function_desc'],
                        'description' => $building['description'],
                        'latitude' => $building['latitude'],
                        'longitude' => $building['longitude'],
                        'land_area' => $building['land_area'],
                        'building_area' => $building['building_area'],
                        'capacity' => $building['capacity'],
                        'facilities' => $building['facilities'],
                        'photo_url' => $building['photo_url'],
                        'detail_url' => $building['detail_url'],
                        'synced_at' => $now,
                    ]
                );

                $formattedContent = $this->normalizer->normalize($this->formatBuildingDocument($building));
                $newHash = hash('sha256', $formattedContent);

                $existingDoc = KnowledgeDocument::query()
                    ->where('source_type', KnowledgeDocument::SOURCE_SIBBM)
                    ->where('source_label', $sourceLabel)
                    ->first();

                $title = "Informasi Bangunan SIB-BM: {$building['name']}";
                $description = $building['function_desc']
                    ? "Data bangunan {$building['name']} ({$building['function_desc']}) dari Sistem Informasi Bangunan Bahrul Maghfiroh."
                    : "Data resmi bangunan {$building['name']} dari SIB-BM.";

                if (! $existingDoc) {
                    $doc = KnowledgeDocument::query()->create([
                        'category_id' => $buildingCategory->id,
                        'title' => $title,
                        'description' => $description,
                        'source_type' => KnowledgeDocument::SOURCE_SIBBM,
                        'source_label' => $sourceLabel,
                        'source_url' => $building['detail_url'],
                        'raw_content' => $formattedContent,
                        'processing_status' => KnowledgeDocument::STATUS_PENDING,
                        'is_active' => true,
                        'uploaded_by' => $actorId,
                    ]);

                    $stats['created']++;
                    $docsToIndex[] = $doc;

                    continue;
                }

                $oldHash = hash('sha256', $this->normalizer->normalize((string) $existingDoc->raw_content));
                $metadataChanged = $existingDoc->title !== $title
                    || $existingDoc->source_url !== $building['detail_url']
                    || ! $existingDoc->is_active
                    || $existingDoc->category_id !== $buildingCategory->id;

                if ($oldHash === $newHash && ! $metadataChanged && $existingDoc->processing_status === KnowledgeDocument::STATUS_INDEXED) {
                    $stats['unchanged']++;

                    continue;
                }

                $existingDoc->update([
                    'category_id' => $buildingCategory->id,
                    'title' => $title,
                    'description' => $description,
                    'source_url' => $building['detail_url'],
                    'raw_content' => $formattedContent,
                    'processing_status' => KnowledgeDocument::STATUS_PENDING,
                    'error_message' => null,
                    'is_active' => true,
                ]);

                if ($oldHash === $newHash && ! $metadataChanged) {
                    $stats['unchanged']++;
                } else {
                    $stats['updated']++;
                }

                $docsToIndex[] = $existingDoc->fresh();
            }

            if ($pruneMissing && $seenSourceLabels !== []) {
                $missingDocs = KnowledgeDocument::query()
                    ->where('source_type', KnowledgeDocument::SOURCE_SIBBM)
                    ->where('is_active', true)
                    ->whereNotIn('source_label', $seenSourceLabels)
                    ->get();

                foreach ($missingDocs as $missingDoc) {
                    $missingDoc->update([
                        'is_active' => false,
                        'processing_status' => KnowledgeDocument::STATUS_PENDING,
                    ]);
                    $this->indexingService->cleanupDocumentVectors($missingDoc);
                    $stats['deactivated']++;
                }
            }
        });

        if ($autoIndex && $docsToIndex !== []) {
            foreach ($docsToIndex as $doc) {
                if (! $this->isEmbeddingConfigured()) {
                    $stats['index_deferred']++;

                    continue;
                }

                try {
                    $this->indexingService->indexDocument($doc);
                    $stats['indexed']++;
                } catch (Throwable $e) {
                    $stats['index_deferred']++;
                    $safeMsg = SecretRedactor::redact($e->getMessage());
                    Log::warning("Indexing dokumen SIB-BM #{$doc->id} ditunda/gagal: {$safeMsg}");
                }
            }
        } elseif ($docsToIndex !== []) {
            $stats['index_deferred'] += count($docsToIndex);
        }

        return $stats;
    }

    /**
     * Format data bangunan SIB-BM menjadi dokumen teks terstruktur untuk pipeline RAG.
     *
     * @param  array<string, mixed>  $b
     */
    private function formatBuildingDocument(array $b): string
    {
        $lines = [
            'Sumber Data: Sistem Informasi Bangunan Bahrul Maghfiroh (SIB-BM)',
            "Kode Bangunan (SIB-BM): {$b['external_id']}",
            "Nama Gedung / Bangunan: {$b['name']}",
        ];

        if (! empty($b['category'])) {
            $lines[] = "Kategori Bangunan: {$b['category']}";
        }
        if (! empty($b['location'])) {
            $lines[] = "Lokasi Bangunan: {$b['location']}";
        }
        if ($b['latitude'] !== null && $b['longitude'] !== null) {
            $lines[] = sprintf('Koordinat Lokasi: %s, %s', $b['latitude'], $b['longitude']);
        }
        if (! empty($b['function_desc'])) {
            $lines[] = "Fungsi Utama Bangunan: {$b['function_desc']}";
        }
        if ($b['land_area'] !== null) {
            $lines[] = 'Luas Tanah: '.number_format((float) $b['land_area'], 2, '.', '').' m2';
        }
        if ($b['building_area'] !== null) {
            $lines[] = 'Luas Bangunan: '.number_format((float) $b['building_area'], 2, '.', '').' m2';
        }
        if ($b['capacity'] !== null) {
            $lines[] = "Kapasitas Tampung: {$b['capacity']} orang";
        }
        if (! empty($b['facilities']) && is_array($b['facilities'])) {
            $lines[] = 'Fasilitas Tersedia: '.implode(', ', $b['facilities']);
        }
        if (! empty($b['description'])) {
            $lines[] = "Deskripsi Bangunan:\n{$b['description']}";
        }
        if (! empty($b['extra_notes'])) {
            $lines[] = "Informasi Pendukung:\n{$b['extra_notes']}";
        }
        if (! empty($b['detail_url'])) {
            $lines[] = "Tautan Detail SIB-BM: {$b['detail_url']}";
        }
        if (! empty($b['photo_url'])) {
            $lines[] = "Foto Bangunan: {$b['photo_url']}";
        }

        return implode("\n", $lines);
    }

    private function isEmbeddingConfigured(): bool
    {
        $driver = (string) config('ai.embedding.driver', 'gemini');
        $key = $driver === 'gemini'
            ? trim((string) config('ai.providers.gemini.api_key', ''))
            : trim((string) config('ai.providers.openai_compatible.api_key', ''));

        return $key !== '' && ! in_array($key, ['MY_GEMINI_API_KEY', 'ISI_API_KEY_GEMINI_ANDA'], true);
    }
}
