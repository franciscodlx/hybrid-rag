<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Embeddings con pgvector + índice HNSW (coseno). Solo PostgreSQL: en otros
 * motores se omite y debe usarse RAG_VECTOR_STORE=memory.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $dimensions = (int) config('rag.embeddings.dimensions');

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement(sprintf(<<<'SQL'
            CREATE TABLE rag_embeddings (
                chunk_id      uuid PRIMARY KEY REFERENCES rag_chunks(id) ON DELETE CASCADE,
                collection_id varchar(64) NOT NULL,
                embedding     vector(%d) NOT NULL
            )
            SQL, $dimensions));
        DB::statement('CREATE INDEX rag_embeddings_collection_idx ON rag_embeddings (collection_id)');
        DB::statement('CREATE INDEX rag_embeddings_hnsw_idx ON rag_embeddings USING hnsw (embedding vector_cosine_ops) WITH (m = 16, ef_construction = 64)');
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS rag_embeddings');
        }
    }
};
