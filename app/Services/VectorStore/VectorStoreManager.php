<?php

namespace App\Services\VectorStore;

use App\Contracts\VectorStoreInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class VectorStoreManager
{
    public function driver(?string $name = null): VectorStoreInterface
    {
        $driver = $name ?? (string) config('ai.vector.driver', 'qdrant');

        return match ($driver) {
            'qdrant' => app(QdrantVectorStore::class),
            'mysql' => app(MysqlVectorStore::class),
            default => throw new InvalidArgumentException("Driver vector storage [{$driver}] tidak didukung."),
        };
    }

    /**
     * Selesaikan driver aktif untuk operasi pipeline dengan fallback aman ke MySQL
     * apabila driver utama Qdrant tidak terjangkau dan fallback dikonfigurasi.
     */
    public function resolveWithFallback(): VectorStoreInterface
    {
        $primaryName = (string) config('ai.vector.driver', 'qdrant');
        $fallbackName = trim((string) config('ai.vector.fallback_driver', ''));
        $primary = $this->driver($primaryName);

        if ($primary->isAvailable()) {
            return $primary;
        }

        if ($fallbackName !== '' && $fallbackName !== $primaryName) {
            Log::warning("Vector store utama [{$primaryName}] tidak dapat dijangkau. Beralih ke fallback [{$fallbackName}].");

            return $this->driver($fallbackName);
        }

        return $primary;
    }
}
