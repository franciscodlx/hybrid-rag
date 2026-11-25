<?php

declare(strict_types=1);

use HybridRag\Domain\Document\Checksum;
use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\Document;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Document\DocumentNotFound;
use HybridRag\Domain\Document\DocumentRepository;
use HybridRag\Infrastructure\Persistence\Database\DatabaseDocumentRepository;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryDocumentRepository;
use Illuminate\Support\Facades\DB;
use Tests\Support\Corpus;

dataset('repositories', [
    'memoria' => fn (): DocumentRepository => new InMemoryDocumentRepository,
    'base de datos' => fn (): DocumentRepository => new DatabaseDocumentRepository(DB::connection()),
]);

function makeDocument(string $collection, string $content, string $at = '2026-11-24 10:00:00'): array
{
    $id = DocumentId::generate();
    $chunks = Corpus::chunks($collection, $id);

    return [new Document(
        $id,
        CollectionId::fromString($collection),
        'Manual',
        'text/plain',
        Checksum::of($content),
        count($chunks),
        new DateTimeImmutable($at, new DateTimeZone('UTC')),
    ), $chunks];
}

it('guarda y recupera documentos respetando la colección', function (DocumentRepository $repository): void {
    [$document, $chunks] = makeDocument('acme', 'a');
    $repository->save($document, $chunks);

    $found = $repository->get(CollectionId::fromString('acme'), $document->id);

    expect($found->title)->toBe('Manual')
        ->and($found->chunkCount)->toBe(count($chunks))
        ->and($found->ingestedAt->format('Y-m-d H:i'))->toBe('2026-11-24 10:00')
        ->and(fn () => $repository->get(CollectionId::fromString('globex'), $document->id))->toThrow(DocumentNotFound::class);
})->with('repositories');

it('busca por checksum dentro de la colección', function (DocumentRepository $repository): void {
    [$document, $chunks] = makeDocument('acme', 'contenido');
    $repository->save($document, $chunks);

    expect($repository->findByChecksum(CollectionId::fromString('acme'), Checksum::of('contenido'))?->id->value)->toBe($document->id->value)
        ->and($repository->findByChecksum(CollectionId::fromString('globex'), Checksum::of('contenido')))->toBeNull();
})->with('repositories');

it('lista del más reciente al más antiguo', function (DocumentRepository $repository): void {
    [$old, $oldChunks] = makeDocument('acme', 'viejo', '2026-01-01 00:00:00');
    [$new, $newChunks] = makeDocument('acme', 'nuevo', '2026-06-01 00:00:00');
    $repository->save($old, $oldChunks);
    $repository->save($new, $newChunks);

    expect(array_map(static fn (Document $d): string => $d->id->value, $repository->all(CollectionId::fromString('acme'))))
        ->toBe([$new->id->value, $old->id->value]);
})->with('repositories');

it('hidrata fragmentos con el título de su documento y omite los inexistentes', function (DocumentRepository $repository): void {
    [$document, $chunks] = makeDocument('acme', 'x');
    $repository->save($document, $chunks);

    $found = $repository->chunksWithSource([$chunks[1]->id, ChunkId::generate()]);

    expect($found)->toHaveCount(1)
        ->and($found[$chunks[1]->id->value]->chunk->text)->toBe($chunks[1]->text)
        ->and($found[$chunks[1]->id->value]->documentTitle)->toBe('Manual');
})->with('repositories');

it('borra el documento y sus fragmentos', function (DocumentRepository $repository): void {
    [$document, $chunks] = makeDocument('acme', 'x');
    $repository->save($document, $chunks);

    $repository->delete($document->id);

    expect($repository->all(CollectionId::fromString('acme')))->toBe([])
        ->and($repository->chunksWithSource([$chunks[0]->id]))->toBe([]);
})->with('repositories');
