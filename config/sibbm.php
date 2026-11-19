<?php

return [
    'enabled' => filter_var(env('SIBBM_API_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'base_url' => rtrim((string) env('SIBBM_API_BASE_URL', ''), '/'),
    'buildings_endpoint' => '/'.ltrim((string) env('SIBBM_API_BUILDINGS_ENDPOINT', '/buildings'), '/'),
    'api_key' => env('SIBBM_API_KEY'),
    'timeout' => (int) env('SIBBM_API_TIMEOUT', 5),
    'cache_ttl' => (int) env('SIBBM_CACHE_TTL', 3600),
    'local_json_path' => env('SIBBM_LOCAL_JSON_PATH', storage_path('app/sibbm_buildings.json')),
];
