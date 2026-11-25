<?php

declare(strict_types=1);

use HybridRag\Domain\Retrieval\Bm25;
use HybridRag\Domain\Shared\InvalidArgument;

it('da más IDF a los términos raros', function (): void {
    $bm25 = new Bm25;

    expect($bm25->idf(1000, 1))->toBeGreaterThan($bm25->idf(1000, 500))
        ->and($bm25->idf(10, 10))->toBeGreaterThan(0.0); // variante Lucene: nunca negativo
});

it('satura la frecuencia del término (rendimientos decrecientes)', function (): void {
    $bm25 = new Bm25;
    $idf = 1.0;

    $one = $bm25->termScore(1, 100, 100.0, $idf);
    $two = $bm25->termScore(2, 100, 100.0, $idf);
    $hundred = $bm25->termScore(100, 100, 100.0, $idf);

    expect($two - $one)->toBeGreaterThan(0.0)
        ->and($hundred)->toBeLessThan($idf * ($bm25->k1 + 1));
});

it('penaliza documentos más largos que el promedio', function (): void {
    $bm25 = new Bm25;

    expect($bm25->termScore(1, 50, 100.0, 1.0))->toBeGreaterThan($bm25->termScore(1, 200, 100.0, 1.0));
});

it('devuelve 0 si el término no aparece', function (): void {
    expect((new Bm25)->termScore(0, 10, 10.0, 3.0))->toBe(0.0);
});

it('valida k1 y b', function (): void {
    expect(fn () => new Bm25(k1: -0.1))->toThrow(InvalidArgument::class)
        ->and(fn () => new Bm25(b: 1.5))->toThrow(InvalidArgument::class);
});
