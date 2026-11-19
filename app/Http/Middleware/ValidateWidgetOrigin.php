<?php

namespace App\Http\Middleware;

use App\Models\ChatbotSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ValidateWidgetOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        $origin = trim((string) $request->headers->get('Origin', ''));
        if ($origin === '') {
            return $next($request);
        }

        if ($this->isOriginAllowed($origin, $request)) {
            return $next($request);
        }

        return response()->json([
            'status' => 'error',
            'error_code' => 'ORIGIN_NOT_ALLOWED',
            'message' => 'Origin atau domain tidak diizinkan untuk mengakses layanan chatbot ini.',
        ], 403);
    }

    private function isOriginAllowed(string $origin, Request $request): bool
    {
        $normalizedOrigin = rtrim(strtolower($origin), '/');
        if ($normalizedOrigin === 'null') {
            return false;
        }

        $allowed = [];

        // 1. Izinkan same-origin host dari request dan APP_URL
        $requestOrigin = rtrim(strtolower($request->getSchemeAndHttpHost()), '/');
        if ($requestOrigin !== '') {
            $allowed[] = $requestOrigin;
        }

        $appUrl = trim((string) config('app.url', ''));
        if ($appUrl !== '' && str_contains($appUrl, '://')) {
            $parsedApp = parse_url($appUrl);
            if (is_array($parsedApp) && ! empty($parsedApp['scheme']) && ! empty($parsedApp['host'])) {
                $port = isset($parsedApp['port']) ? ':'.$parsedApp['port'] : '';
                $allowed[] = strtolower("{$parsedApp['scheme']}://{$parsedApp['host']}{$port}");
            }
        }

        // 2. Konfigurasi CORS (ALLOWED_WIDGET_ORIGINS)
        $corsOrigins = config('cors.allowed_origins', []);
        if (is_array($corsOrigins)) {
            foreach ($corsOrigins as $entry) {
                $clean = rtrim(strtolower(trim((string) $entry)), '/');
                if ($clean !== '') {
                    $allowed[] = $clean;
                }
            }
        }

        // 3. Pengaturan allowed_origins di tabel chatbot_settings (bila tersedia)
        try {
            $dbOrigins = ChatbotSetting::getValue('allowed_origins');
            if (is_string($dbOrigins) && trim($dbOrigins) !== '') {
                foreach (explode(',', $dbOrigins) as $entry) {
                    $clean = rtrim(strtolower(trim($entry)), '/');
                    if ($clean !== '') {
                        $allowed[] = $clean;
                    }
                }
            }
        } catch (Throwable) {
            // Abaikan bila tabel belum dimigrasi saat boot awal
        }

        $allowed = array_values(array_unique($allowed));

        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($normalizedOrigin, $allowed, true);
    }
}
