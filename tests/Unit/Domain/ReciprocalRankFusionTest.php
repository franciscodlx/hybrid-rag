<?php

declare(strict_types=1);

use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Retrieval\Fusion\ReciprocalRankFusion;
use HybridRag\Domain\Retrieval\Hit;
use HybridRag\Domain\Retrieval\RankedList;
use HybridRag\Domain\Shared\InvalidArgument;

/**
 * @param  list<ChunkId>  $ids  en orden de mejor a peor
 */
function rankedList(string $retriever, array $ids): RankedList
{
    $count = count($ids);

    return new RankedList($retriever, array_map(
        static fn (ChunkId $id, int $i): Hit => new Hit($id, (float) ($count - $i)),
        $ids,
        array_keys($ids),
    ));
}

it('aplica la fórmula Σ 1/(k + rank)', function (): void {
    [$a, $b] = [ChunkId::generate(), ChunkId::generate()];

    $fused = (new ReciprocalRankFusion(k: 60))->fuse([
        rankedList('bm25', [$a, $b]),
        rankedList('vector', [$b, $a]),
    ]);

    expect($fused[0]->score)->toEqualWithDelta(1 / 61 + 1 / 62, 1e-12)
        ->and($fused[1]->score)->toEqualWithDelta(1 / 61 + 1 / 62, 1e-12);
});

it('premia el consenso: aparecer en ambas listas supera a ser primero en una sola', function (): void {
    [$onlyLexical, $both, $onlyVector] = [ChunkId::generate(), ChunkId::generate(), ChunkId::generate()];

    $fused = (new ReciprocalRankFusion)->fuse([
        rankedList('bm25', [$onlyLexical, $both]),
        rankedList('vector', [$onlyVector, $both]),
    ]);

    expect($fused[0]->chunkId->equals($both))->toBeTrue()
        ->and($fused[0]->ranks)->toBe(['bm25' => 2, 'vector' => 2]);
});

it('ignora la escala de las puntuaciones originales y usa solo la posición', function (): void {
    [$a, $b] = [ChunkId::generate(), ChunkId::generate()];

    $huge = new RankedList('bm25', [new Hit($a, 9_999.0), new Hit($b, 9_998.0)]);
    $tiny = new RankedList('bm25', [new Hit($a, 0.002), new Hit($b, 0.001)]);

    $rrf = new ReciprocalRankFusion;
    expect($rrf->fuse([$huge])[0]->score)->toBe($rrf->fuse([$tiny])[0]->score);
});

it('respeta los pesos por recuperador', function (): void {
    [$lexical, $semantic] = [ChunkId::generate(), ChunkId::generate()];

    $fused = (new ReciprocalRankFusion(weights: ['bm25' => 0.2, 'vector' => 1.0]))->fuse([
        rankedList('bm25', [$lexical]),
        rankedList('vector', [$semantic]),
    ]);

    expect($fused[0]->chunkId->equals($semantic))->toBeTrue();
});

it('devuelve vacío si no hay listas o están vacías', function (): void {
    expect((new ReciprocalRankFusion)->fuse([]))->toBe([])
        ->and((new ReciprocalRankFusion)->fuse([RankedList::empty('bm25')]))->toBe([]);
});

it('rechaza parámetros inválidos', function (): void {
    expect(fn () => new ReciprocalRankFusion(k: 0))->toThrow(InvalidArgument::class)
        ->and(fn () => new ReciprocalRankFusion(weights: ['bm25' => -1.0]))->toThrow(InvalidArgument::class);
});
