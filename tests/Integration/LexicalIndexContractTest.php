<?php

declare(strict_types=1);

use HybridRag\Domain\Document\Checksum;
use HybridRag\Domain\Document\Chunk;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\Document;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Retrieval\Hit;
use HybridRag\Domain\Retrieval\LexicalIndex;
use HybridRag\Domain\Text\StandardAnalyzer;
use HybridRag\Infrastructure\Persistence\Database\DatabaseDocumentRepository;
use HybridRag\Infrastructure\Persistence\Database\SqlBm25Index;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryLexicalIndex;
use Illuminate\Support\Facades\DB;
use Tests\Support\Corpus;

/**
 * Test de contrato: el índice SQL debe producir EXACTAMENTE el mismo ranking
 * y las mismas puntuaciones que la implementación de referencia en memoria.
 */
function persistDocument(string $collection): array
{
    $documentId = DocumentId::generate();
    $chunks = Corpus::chunks($collection, $documentId);

    (new DatabaseDocumentRepository(DB::connection()))->save(new Document(
        $documentId,
        CollectionId::fromString($collection),
        "Doc {$collection}",
        'text/plain',
        Checksum::of($collection),
        count($chunks),
        new DateTimeImmutable,
    ), $chunks);

    return [$documentId, $chunks];
}

/**
 * @return array<string, float> chunk text → score
 */
function scoresByText(LexicalIndex $index, array $chunks, string $collection, string $query): array
{
    $texts = [];
    foreach ($chunks as $chunk) {
        /** @var Chunk $chunk */
        $texts[$chunk->id->value] = $chunk->text;
    }

    $scores = [];
    foreach ($index->search(CollectionId::fromString($collection), $query, 10)->hits as $hit) {
        /** @var Hit $hit */
        $scores[$texts[$hit->chunkId->value]] = $hit->score;
    }

    return $scores;
}

it('SqlBm25Index coincide con la implementación de referencia', function (string $query): void {
    [, $chunks] = persistDocument('acme');
    [, $noise] = persistDocument('otra'); // otra colección no debe alterar df ni avgdl

    $sql = new SqlBm25Index(DB::connection(), new StandardAnalyzer);
    $sql->index([...$chunks, ...$noise]);

    $reference = new InMemoryLexicalIndex(new StandardAnalyzer);
    $reference->index([...$chunks, ...$noise]);

    $expected = scoresByText($reference, $chunks, 'acme', $query);
    $actual = scoresByText($sql, $chunks, 'acme', $query);

    expect(array_keys($actual))->toBe(array_keys($expected));
    foreach ($expected as $text => $score) {
        expect($actual[$text])->toEqualWithDelta($score, 1e-9);
    }
})->with([
    'término exacto' => ['ERR-4521'],
    'varios términos' => ['política de vacaciones días'],
    'acentos' => ['politica seguridad contrasenas'],
]);

it('elimina del índice los fragmentos de un documento', function (): void {
    [$documentId, $chunks] = persistDocument('acme');
    $index = new SqlBm25Index(DB::connection(), new StandardAnalyzer);
    $index->index($chunks);

    $index->remove($documentId);

    expect($index->search(CollectionId::fromString('acme'), 'vacaciones', 5)->isEmpty())->toBeTrue()
        ->and(DB::table('rag_lexical_postings')->count())->toBe(0);
});

it('devuelve vacío para consultas compuestas solo de stopwords', function (): void {
    [, $chunks] = persistDocument('acme');
    $index = new SqlBm25Index(DB::connection(), new StandardAnalyzer);
    $index->index($chunks);

    expect($index->search(CollectionId::fromString('acme'), 'de la que el', 5)->isEmpty())->toBeTrue();
});
