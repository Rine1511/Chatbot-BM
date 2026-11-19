<?php

namespace App\Services\SibBm;

use App\Exceptions\SibBmException;
use App\Support\SecretRedactor;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SibBmClient
{
    public function isApiConfigured(): bool
    {
        return (bool) config('sibbm.enabled', false)
            && trim((string) config('sibbm.base_url', '')) !== '';
    }

    public function hasLocalSnapshot(?string $path = null): bool
    {
        $resolved = $path ?: (string) config('sibbm.local_json_path', storage_path('app/sibbm_buildings.json'));

        return $resolved !== '' && is_file($resolved) && is_readable($resolved);
    }

    /**
     * Ambil data bangunan dari sumber yang tersedia ('auto' | 'api' | 'local').
     *
     * @return array{source: string, buildings: list<array<string, mixed>>}
     */
    public function fetchBuildings(string $source = 'auto', ?string $localPath = null): array
    {
        if ($source === 'api') {
            return [
                'source' => 'api',
                'buildings' => $this->fetchFromApi(),
            ];
        }

        if ($source === 'local') {
            return [
                'source' => 'local_json',
                'buildings' => $this->fetchFromLocalSnapshot($localPath),
            ];
        }

        if ($this->isApiConfigured()) {
            return [
                'source' => 'api',
                'buildings' => $this->fetchFromApi(),
            ];
        }

        if ($this->hasLocalSnapshot($localPath)) {
            return [
                'source' => 'local_json',
                'buildings' => $this->fetchFromLocalSnapshot($localPath),
            ];
        }

        return [
            'source' => 'unconfigured',
            'buildings' => [],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fetchFromApi(): array
    {
        $enabled = (bool) config('sibbm.enabled', false);
        $baseUrl = rtrim((string) config('sibbm.base_url', ''), '/');
        $endpoint = '/'.ltrim((string) config('sibbm.buildings_endpoint', '/buildings'), '/');
        $apiKey = trim((string) config('sibbm.api_key', ''));
        $timeout = max(1, (int) config('sibbm.timeout', 5));

        if (! $enabled || $baseUrl === '') {
            throw new SibBmException('Integrasi REST API SIB-BM belum diaktifkan (SIBBM_API_ENABLED / SIBBM_API_BASE_URL).');
        }

        $url = "{$baseUrl}{$endpoint}";

        try {
            $request = Http::timeout($timeout)->acceptJson();

            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->retry(
                2,
                300,
                function ($exception): bool {
                    if ($exception instanceof ConnectionException) {
                        return true;
                    }

                    if ($exception instanceof RequestException && $exception->response) {
                        return $exception->response->status() >= 500;
                    }

                    return false;
                },
                throw: false
            )->get($url);
        } catch (ConnectionException $e) {
            throw new SibBmException('Koneksi ke REST API SIB-BM timeout atau tidak dapat dijangkau.', 0, $e);
        }

        if (! $response->successful()) {
            $detail = SecretRedactor::redact((string) ($response->json('message') ?? ''));
            $suffix = $detail !== '' ? ": {$detail}" : '';
            throw new SibBmException("REST API SIB-BM mengembalikan HTTP {$response->status()}{$suffix}.");
        }

        $decoded = $response->json();
        if (! is_array($decoded)) {
            throw new SibBmException('Format respons REST API SIB-BM tidak valid (bukan JSON array/object).');
        }

        return $this->normalizePayload($decoded);
    }

    /**
     * Mekanisme integrasi sementara melalui snapshot JSON terstruktur saat REST API SIB-BM belum tersedia.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchFromLocalSnapshot(?string $path = null): array
    {
        $filePath = $path ?: (string) config('sibbm.local_json_path', storage_path('app/sibbm_buildings.json'));
        if ($filePath === '' || ! is_file($filePath) || ! is_readable($filePath)) {
            throw new SibBmException("Berkas snapshot JSON SIB-BM tidak ditemukan di: {$filePath}");
        }

        $raw = file_get_contents($filePath);
        if ($raw === false || trim($raw) === '') {
            throw new SibBmException('Berkas snapshot JSON SIB-BM kosong atau gagal dibaca.');
        }

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new SibBmException('Berkas snapshot JSON SIB-BM tidak valid: '.json_last_error_msg());
        }

        return $this->normalizePayload($decoded);
    }

    /**
     * Normalisasi variasi payload JSON SIB-BM ke struktur bangunan kanonik.
     *
     * @param  array<mixed>  $decoded
     * @return list<array<string, mixed>>
     */
    public function normalizePayload(array $decoded): array
    {
        $items = $decoded;
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $items = $decoded['data'];
        } elseif (isset($decoded['buildings']) && is_array($decoded['buildings'])) {
            $items = $decoded['buildings'];
        }

        if (! is_array($items)) {
            return [];
        }

        $normalized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $externalId = trim((string) ($item['external_id'] ?? $item['id'] ?? $item['code'] ?? ''));
            $name = trim((string) ($item['name'] ?? $item['nama_gedung'] ?? $item['building_name'] ?? ''));

            if ($externalId === '' || $name === '') {
                continue;
            }

            $facilitiesRaw = $item['facilities'] ?? $item['fasilitas'] ?? [];
            if (is_string($facilitiesRaw)) {
                $facilities = array_values(array_filter(array_map('trim', explode(',', $facilitiesRaw)), fn ($v) => $v !== ''));
            } elseif (is_array($facilitiesRaw)) {
                $facilities = array_values(array_filter(array_map(static fn ($v) => trim((string) $v), $facilitiesRaw), fn ($v) => $v !== ''));
            } else {
                $facilities = [];
            }

            $normalized[] = [
                'external_id' => substr($externalId, 0, 100),
                'name' => substr($name, 0, 255),
                'category' => $this->nullableString($item['category'] ?? $item['kategori'] ?? null, 120),
                'location' => $this->nullableString($item['location'] ?? $item['lokasi'] ?? $item['address'] ?? null, 255),
                'function_desc' => $this->nullableString($item['function_desc'] ?? $item['function'] ?? $item['fungsi'] ?? null, 255),
                'description' => $this->nullableText($item['description'] ?? $item['deskripsi'] ?? null),
                'latitude' => $this->nullableFloat($item['latitude'] ?? $item['lat'] ?? null),
                'longitude' => $this->nullableFloat($item['longitude'] ?? $item['lng'] ?? $item['lon'] ?? null),
                'land_area' => $this->nullableFloat($item['land_area'] ?? $item['luas_tanah'] ?? null),
                'building_area' => $this->nullableFloat($item['building_area'] ?? $item['luas_bangunan'] ?? null),
                'capacity' => $this->nullableInt($item['capacity'] ?? $item['kapasitas'] ?? null),
                'facilities' => $facilities,
                'photo_url' => $this->nullableUrl($item['photo_url'] ?? $item['foto_url'] ?? null),
                'detail_url' => $this->nullableUrl($item['detail_url'] ?? $item['url'] ?? null),
                'extra_notes' => $this->nullableText($item['notes'] ?? $item['keterangan'] ?? null),
            ];
        }

        return $normalized;
    }

    private function nullableString(mixed $val, int $maxLen): ?string
    {
        $str = trim((string) ($val ?? ''));

        return $str === '' ? null : substr($str, 0, $maxLen);
    }

    private function nullableText(mixed $val): ?string
    {
        $str = trim((string) ($val ?? ''));

        return $str === '' ? null : $str;
    }

    private function nullableFloat(mixed $val): ?float
    {
        if ($val === null || $val === '' || ! is_numeric($val)) {
            return null;
        }

        return (float) $val;
    }

    private function nullableInt(mixed $val): ?int
    {
        if ($val === null || $val === '' || ! is_numeric($val)) {
            return null;
        }

        $intVal = (int) $val;

        return $intVal >= 0 ? $intVal : null;
    }

    private function nullableUrl(mixed $val): ?string
    {
        $str = trim((string) ($val ?? ''));
        if ($str === '') {
            return null;
        }

        return filter_var($str, FILTER_VALIDATE_URL) ? substr($str, 0, 500) : null;
    }
}
