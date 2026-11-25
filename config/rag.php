<?php

declare(strict_types=1);

return [

    /*
    | Token Bearer requerido por la API. Si está vacío la API rechaza todo
    | (fail-closed): los documentos son privados por defecto.
    */
    'api_token' => env('RAG_API_TOKEN'),

    'stores' => [
        // database = PostgreSQL (o SQLite para desarrollo) · memory = solo tests
        'documents' => env('RAG_STORE', 'database'),
        // pgvector = PostgreSQL + extensión vector · memory = solo tests
        'vector' => env('RAG_VECTOR_STORE', 'pgvector'),
    ],

    'embeddings' => [
        // voyage | ollama | hashing (determinista, sin red, NO semántico)
        'driver' => env('RAG_EMBEDDINGS', 'voyage'),
        // Debe coincidir con la columna vector(N); cambiarlo exige re-indexar.
        'dimensions' => (int) env('RAG_EMBEDDING_DIMENSIONS', 1024),

        'voyage' => [
            'api_key' => env('VOYAGE_API_KEY'),
            'model' => env('VOYAGE_MODEL', 'voyage-3.5'),
            'batch_size' => 64,
        ],

        'ollama' => [
            'url' => env('OLLAMA_URL', 'http://localhost:11434'),
            'model' => env('OLLAMA_EMBED_MODEL', 'nomic-embed-text'),
            'document_prefix' => env('OLLAMA_DOCUMENT_PREFIX', 'search_document: '),
            'query_prefix' => env('OLLAMA_QUERY_PREFIX', 'search_query: '),
        ],
    ],

    'generation' => [
        // claude | extractive (sin LLM, para demos/CI)
        'driver' => env('RAG_GENERATOR', 'claude'),

        'claude' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('RAG_CLAUDE_MODEL', 'claude-opus-5-5'),
            'effort' => env('RAG_CLAUDE_EFFORT', 'medium'),
            'max_tokens' => (int) env('RAG_CLAUDE_MAX_TOKENS', 16000),
            // Reintento en servidor con otro modelo si el principal declina.
            'server_side_fallback' => (bool) env('RAG_CLAUDE_FALLBACK', true),
        ],
    ],

    'chunking' => [
        'max_characters' => (int) env('RAG_CHUNK_MAX_CHARS', 1200),
        'overlap_characters' => (int) env('RAG_CHUNK_OVERLAP_CHARS', 150),
    ],

    'retrieval' => [
        'candidates_per_retriever' => (int) env('RAG_CANDIDATES', 40),
        'rrf_k' => (int) env('RAG_RRF_K', 60),
        'weights' => [
            'bm25' => (float) env('RAG_WEIGHT_BM25', 1.0),
            'vector' => (float) env('RAG_WEIGHT_VECTOR', 1.0),
        ],
        'bm25' => [
            'k1' => (float) env('RAG_BM25_K1', 1.2),
            'b' => (float) env('RAG_BM25_B', 0.75),
        ],
        'hnsw_ef_search' => (int) env('RAG_HNSW_EF_SEARCH', 100),
    ],

    'uploads' => [
        'max_kilobytes' => (int) env('RAG_UPLOAD_MAX_KB', 20480),
        'mimetypes' => ['text/plain', 'text/markdown', 'text/x-markdown', 'text/csv', 'application/pdf'],
    ],
];
