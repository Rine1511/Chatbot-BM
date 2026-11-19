<?php

$embeddingDimensions = (int) env('AI_EMBEDDING_DIMENSIONS', 768);

return [

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi LLM (Generasi Jawaban)
    |--------------------------------------------------------------------------
    */
    'llm' => [
        'driver' => env('AI_LLM_DRIVER', 'gemini'),
        'model' => env('AI_LLM_MODEL', 'gemini-2.5-flash'),
        'temperature' => (float) env('AI_LLM_TEMPERATURE', 0.2),
        'max_tokens' => (int) env('AI_LLM_MAX_TOKENS', 1024),
        'timeout' => (int) env('AI_LLM_TIMEOUT', 30),
        'retry_times' => (int) env('AI_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('AI_RETRY_SLEEP_MS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Embedding Model
    |--------------------------------------------------------------------------
    | gemini-embedding-001 mendukung outputDimensionality (mis. 768, 1536, 3072).
    | Nilai dimensions di bawah ini menjadi sumber tunggal untuk API embedding
    | dan ukuran vektor pada collection Qdrant.
    */
    'embedding' => [
        'driver' => env('AI_EMBEDDING_DRIVER', 'gemini'),
        'model' => env('AI_EMBEDDING_MODEL', 'gemini-embedding-001'),
        'dimensions' => $embeddingDimensions,
        'timeout' => (int) env('AI_EMBEDDING_TIMEOUT', 20),
        'retry_times' => (int) env('AI_RETRY_TIMES', 2),
        'retry_sleep_ms' => (int) env('AI_RETRY_SLEEP_MS', 500),
    ],

    /*
    |--------------------------------------------------------------------------
    | Konfigurasi Vector Storage (qdrant | mysql)
    |--------------------------------------------------------------------------
    */
    'vector' => [
        'driver' => env('AI_VECTOR_DRIVER', 'qdrant'),
        'fallback_driver' => env('AI_VECTOR_FALLBACK_DRIVER', 'mysql'),
        'qdrant' => [
            'host' => rtrim((string) env('QDRANT_HOST', 'http://127.0.0.1:6333'), '/'),
            'api_key' => env('QDRANT_API_KEY'),
            'collection' => env('QDRANT_COLLECTION', 'bm_knowledge_chunks'),
            'vector_size' => $embeddingDimensions,
            'distance' => env('QDRANT_DISTANCE', 'Cosine'),
            'timeout' => (int) env('QDRANT_TIMEOUT', 10),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Parameter Pipeline RAG
    |--------------------------------------------------------------------------
    */
    'rag' => [
        'top_k' => (int) env('RAG_TOP_K', 5),
        'similarity_threshold' => (float) env('RAG_SIMILARITY_THRESHOLD', 0.65),
        'chunk_size' => (int) env('RAG_CHUNK_SIZE', 800),
        'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 120),
        'queue_indexing' => filter_var(env('RAG_QUEUE_INDEXING', false), FILTER_VALIDATE_BOOLEAN),
        'rate_limit_per_minute' => max(1, (int) env('CHAT_RATE_LIMIT_PER_MINUTE', 20)),
    ],

    /*
    |--------------------------------------------------------------------------
    | Kredensial & Endpoint Provider AI (Server-Side Only)
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => rtrim((string) env('GEMINI_API_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'), '/'),
        ],
        'openai_compatible' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => rtrim((string) env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
            'llm_model' => env('OPENAI_LLM_MODEL', 'gpt-4o-mini'),
            'embedding_model' => env('OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'embedding_dimensions' => (int) env('OPENAI_EMBEDDING_DIMENSIONS', $embeddingDimensions),
        ],
    ],

];
