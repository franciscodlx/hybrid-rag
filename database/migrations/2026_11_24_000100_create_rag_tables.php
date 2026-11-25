<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documentos, fragmentos e índice invertido BM25. SQL portable
 * (PostgreSQL en producción, SQLite en tests).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('collection_id', 64);
            $table->string('title', 255);
            $table->string('media_type', 100);
            $table->char('checksum', 64);
            $table->unsignedInteger('chunk_count');
            $table->timestampTz('ingested_at', 6);

            $table->unique(['collection_id', 'checksum']);
            $table->index(['collection_id', 'ingested_at']);
        });

        Schema::create('rag_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('document_id')->constrained('rag_documents')->cascadeOnDelete();
            $table->string('collection_id', 64);
            $table->unsignedInteger('position');
            $table->unsignedInteger('page')->nullable();
            $table->text('text');

            $table->unique(['document_id', 'position']);
        });

        Schema::create('rag_lexical_docs', function (Blueprint $table): void {
            $table->foreignUuid('chunk_id')->primary()->constrained('rag_chunks')->cascadeOnDelete();
            $table->string('collection_id', 64)->index();
            $table->unsignedInteger('length');
        });

        Schema::create('rag_lexical_postings', function (Blueprint $table): void {
            $table->foreignUuid('chunk_id')->constrained('rag_chunks')->cascadeOnDelete();
            $table->string('term', 128);
            $table->string('collection_id', 64);
            $table->unsignedInteger('tf');

            $table->primary(['chunk_id', 'term']);
            // Ruta de consulta: (colección, término) → postings.
            $table->index(['collection_id', 'term']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_lexical_postings');
        Schema::dropIfExists('rag_lexical_docs');
        Schema::dropIfExists('rag_chunks');
        Schema::dropIfExists('rag_documents');
    }
};
