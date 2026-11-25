<?php

declare(strict_types=1);

use HybridRag\Application\Ingestion\EmptyDocument;
use HybridRag\Application\Ingestion\IngestDocument;
use HybridRag\Application\Ingestion\UnsupportedMediaType;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Shared\InvalidArgument;
use Tests\Support\Corpus;
use Tests\Support\InMemoryRag;

it('fragmenta, vectoriza e indexa un documento', function (): void {
    $rag = new InMemoryRag;

    $result = ($rag->ingest)(new IngestDocument('acme', '  Manual  ', implode("\n\n", Corpus::TEXTS), 'text/plain'));

    expect($result->created)->toBeTrue()
        ->and($result->document->title)->toBe('Manual')
        ->and($result->document->chunkCount)->toBeGreaterThan(1)
        ->and($rag->documents->all(CollectionId::fromString('acme')))->toHaveCount(1);
});

it('es idempotente: el mismo contenido no se re-indexa ni se re-vectoriza', function (): void {
    $rag = new InMemoryRag;
    $command = new IngestDocument('acme', 'Manual', 'Contenido del manual de bienvenida.', 'text/plain');

    $first = ($rag->ingest)($command);
    $second = ($rag->ingest)($command);

    expect($second->created)->toBeFalse()
        ->and($second->document->id->equals($first->document->id))->toBeTrue()
        ->and($rag->embedder->documentCalls)->toBe(1);
});

it('permite el mismo contenido en colecciones distintas', function (): void {
    $rag = new InMemoryRag;

    $a = ($rag->ingest)(new IngestDocument('acme', 'Doc', 'Mismo texto.', 'text/plain'));
    $b = ($rag->ingest)(new IngestDocument('globex', 'Doc', 'Mismo texto.', 'text/plain'));

    expect($a->created && $b->created)->toBeTrue();
});

it('rechaza documentos vacíos, formatos no soportados y colecciones inválidas', function (): void {
    $rag = new InMemoryRag;

    expect(fn () => ($rag->ingest)(new IngestDocument('acme', 'Vacío', "  \n\n ", 'text/plain')))->toThrow(EmptyDocument::class)
        ->and(fn () => ($rag->ingest)(new IngestDocument('acme', 'X', 'x', 'image/png')))->toThrow(UnsupportedMediaType::class)
        ->and(fn () => ($rag->ingest)(new IngestDocument('../etc', 'X', 'x', 'text/plain')))->toThrow(InvalidArgument::class);
});
