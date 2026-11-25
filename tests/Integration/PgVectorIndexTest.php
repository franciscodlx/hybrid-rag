<?php

declare(strict_types=1);

use HybridRag\Domain\Document\Checksum;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\Document;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Embedding\Embedding;
use HybridRag\Domain\Shared\InvalidArgument;
use HybridRag\Domain\Text\StandardAnalyzer;
use HybridRag\Infrastructure\Embedding\HashingEmbedder;
use HybridRag\Infrastructure\Persistence\Database\DatabaseDocumentRepository;
use HybridRag\Infrastructure\Persistence\Database\PgVectorIndex;
use Illuminate\Support\Facades\DB;
use Tests\Support\Corpus;

/**
 * Requiere PostgreSQL + pgvector (docker compose up -d). Se omite si
 * RAG_PGVECTOR_TEST_HOST no está definido, para que la suite corra en
 * cualquier máquina.
 */
beforeEach(function (): void {
    $host = getenv('RAG_PGVECTOR_TEST_HOST');
    if ($host === false || $host === '') {
        $this->markTestSkipped('Defina RAG_PGVECTOR_TEST_HOST para ejecutar los tests de pgvector.');
    }

    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.host' => $host,
        'database.connections.pgsql.port' => getenv('RAG_PGVECTOR_TEST_PORT') ?: '5432',
        'database.connections.pgsql.database' => getenv('RAG_PGVECTOR_TEST_DATABASE') ?: 'hybrid_rag_testing',
        'database.connections.pgsql.username' => getenv('RAG_PGVECTOR_TEST_USERNAME') ?: 'rag',
        'database.connections.pgsql.password' => getenv('RAG_PGVECTOR_TEST_PASSWORD') ?: 'rag',
        'rag.embeddings.dimensions' => 256,
    ]);
    DB::purge();
    $this->artisan('migrate:fresh');
});

it('recupera por similitud coseno filtrando por colección', function (): void {
    $embedder = new HashingEmbedder(new StandardAnalyzer, 256);
    $index = new PgVectorIndex(DB::connection());
    $repository = new DatabaseDocumentRepository(DB::connection());

    foreach (['acme', 'globex'] as $collection) {
        $id = DocumentId::generate();
        $chunks = Corpus::chunks($collection, $id);
        $repository->save(new Document($id, CollectionId::fromString($collection), 'Doc', 'text/plain', Checksum::of($collection), count($chunks), new DateTimeImmutable), $chunks);
        $index->upsert($chunks, $embedder->embedDocuments(array_map(static fn ($c) => $c->text, $chunks)));
    }

    $query = $embedder->embedQuery('certificado TLS expirado');
    $results = $index->search(CollectionId::fromString('acme'), $query, 3);

    expect($results->hits)->toHaveCount(3)
        ->and($repository->chunksWithSource([$results->hits[0]->chunkId])[$results->hits[0]->chunkId->value]->chunk->text)
        ->toContain('ERR-4521')
        ->and($results->hits[0]->score)->toBeGreaterThan($results->hits[2]->score);
});

it('rechaza un número de embeddings distinto al de fragmentos', function (): void {
    expect(fn () => (new PgVectorIndex(DB::connection()))->upsert(Corpus::chunks(), [new Embedding([1.0])]))
        ->toThrow(InvalidArgument::class);
});
