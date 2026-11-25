<?php

declare(strict_types=1);

use HybridRag\Domain\Document\Checksum;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Embedding\Embedding;
use HybridRag\Domain\Shared\InvalidArgument;

it('valida el identificador de colección', function (string $value, bool $valid): void {
    $create = fn () => CollectionId::fromString($value);

    $valid ? expect($create()->value)->toBe($value) : expect($create)->toThrow(InvalidArgument::class);
})->with([
    ['acme', true],
    ['rrhh_2026', true],
    ['', false],
    ['Mayúsculas', false],
    ['../etc', false],
    [str_repeat('a', 65), false],
]);

it('genera UUIDs válidos y los compara por valor', function (): void {
    $id = DocumentId::generate();

    expect(DocumentId::fromString(strtoupper($id->value))->equals($id))->toBeTrue()
        ->and(fn () => DocumentId::fromString('no-uuid'))->toThrow(InvalidArgument::class);
});

it('calcula checksum estable', function (): void {
    expect(Checksum::of('hola')->value)->toBe(hash('sha256', 'hola'));
});

it('calcula similitud coseno', function (): void {
    $a = new Embedding([1, 0]);

    expect($a->cosineSimilarity(new Embedding([1, 0])))->toEqualWithDelta(1.0, 1e-12)
        ->and($a->cosineSimilarity(new Embedding([0, 1])))->toEqualWithDelta(0.0, 1e-12)
        ->and($a->cosineSimilarity(new Embedding([-2, 0])))->toEqualWithDelta(-1.0, 1e-12)
        ->and(fn () => $a->cosineSimilarity(new Embedding([1, 0, 0])))->toThrow(InvalidArgument::class)
        ->and(fn () => new Embedding([NAN]))->toThrow(InvalidArgument::class);
});
