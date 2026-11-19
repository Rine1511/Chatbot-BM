<?php

return [
    'paths' => ['api/v1/*'],
    'allowed_methods' => ['GET', 'POST', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ALLOWED_WIDGET_ORIGINS', 'https://bahrulmaghfirohmalang.or.id'))
    ))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'Accept', 'X-Requested-With', 'X-Widget-Session', 'Origin'],
    'exposed_headers' => ['Retry-After'],
    'max_age' => 3600,
    'supports_credentials' => false,
];
