<?php

namespace App\Providers;

use App\Contracts\EmbeddingProviderInterface;
use App\Contracts\LlmProviderInterface;
use App\Contracts\VectorStoreInterface;
use App\Services\AI\EmbeddingProviderManager;
use App\Services\AI\LlmProviderManager;
use App\Services\VectorStore\VectorStoreManager;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(EmbeddingProviderManager::class);
        $this->app->singleton(LlmProviderManager::class);
        $this->app->singleton(VectorStoreManager::class);

        $this->app->bind(
            EmbeddingProviderInterface::class,
            fn ($app) => $app->make(EmbeddingProviderManager::class)->driver()
        );

        $this->app->bind(
            LlmProviderInterface::class,
            fn ($app) => $app->make(LlmProviderManager::class)->driver()
        );

        $this->app->bind(
            VectorStoreInterface::class,
            fn ($app) => $app->make(VectorStoreManager::class)->driver()
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('chat-start', function (Request $request) {
            return Limit::perMinute(15)
                ->by('chat-start:'.$request->ip())
                ->response(function (Request $req, array $headers) {
                    return response()->json([
                        'status' => 'error',
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Terlalu banyak permintaan memulai percakapan. Silakan coba beberapa saat lagi.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('chat-message', function (Request $request) {
            $perMinute = max(1, (int) config('ai.rag.rate_limit_per_minute', 20));
            $sessionToken = strtolower(trim((string) ($request->input('session_token') ?: $request->header('X-Widget-Session', ''))));
            $key = $sessionToken !== '' ? "chat-msg:{$sessionToken}:{$request->ip()}" : "chat-msg-ip:{$request->ip()}";

            return [
                Limit::perMinute($perMinute)
                    ->by($key)
                    ->response(function (Request $req, array $headers) {
                        return response()->json([
                            'status' => 'error',
                            'error_code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Batas pengiriman pesan tercapai. Mohon tunggu sebentar sebelum mengirim pertanyaan berikutnya.',
                        ], 429, $headers);
                    }),
                Limit::perHour(120)
                    ->by('chat-msg-hour:'.$request->ip())
                    ->response(function (Request $req, array $headers) {
                        return response()->json([
                            'status' => 'error',
                            'error_code' => 'RATE_LIMIT_EXCEEDED',
                            'message' => 'Batas penggunaan per jam tercapai. Silakan coba lagi nanti.',
                        ], 429, $headers);
                    }),
            ];
        });

        RateLimiter::for('chat-read', function (Request $request) {
            return Limit::perMinute(60)
                ->by('chat-read:'.$request->ip())
                ->response(function (Request $req, array $headers) {
                    return response()->json([
                        'status' => 'error',
                        'error_code' => 'RATE_LIMIT_EXCEEDED',
                        'message' => 'Terlalu banyak permintaan riwayat percakapan. Silakan tunggu sebentar.',
                    ], 429, $headers);
                });
        });
    }
}
