<?php

declare(strict_types=1);

use HybridRag\Domain\Shared\InvalidArgument;
use HybridRag\Domain\Text\ChunkingOptions;
use HybridRag\Domain\Text\RecursiveChunker;
use HybridRag\Domain\Text\TextSegment;

function paragraphs(int $count, int $sentencesEach = 6): string
{
    $paragraphs = [];
    for ($p = 1; $p <= $count; $p++) {
        $sentences = [];
        for ($s = 1; $s <= $sentencesEach; $s++) {
            $sentences[] = "Párrafo {$p} oración {$s} con algo de contenido de relleno.";
        }
        $paragraphs[] = implode(' ', $sentences);
    }

    return implode("\n\n", $paragraphs);
}

it('devuelve un único fragmento si el texto cabe', function (): void {
    $chunks = (new RecursiveChunker)->chunk([new TextSegment('Texto corto.')]);

    expect($chunks)->toHaveCount(1)->and($chunks[0]->text)->toBe('Texto corto.');
});

it('nunca supera el tamaño máximo', function (): void {
    $chunker = new RecursiveChunker(new ChunkingOptions(maxCharacters: 300, overlapCharacters: 50));

    foreach ($chunker->chunk([new TextSegment(paragraphs(10))]) as $chunk) {
        expect(mb_strlen($chunk->text))->toBeLessThanOrEqual(300);
    }
});

it('no pierde contenido: cada oración aparece en algún fragmento', function (): void {
    $text = paragraphs(5);
    $chunks = (new RecursiveChunker(new ChunkingOptions(300, 50)))->chunk([new TextSegment($text)]);
    $joined = implode(' ', array_map(static fn (TextSegment $c): string => $c->text, $chunks));

    preg_match_all('/Párrafo \d+ oración \d+/u', $text, $expected);
    foreach ($expected[0] as $sentence) {
        expect($joined)->toContain($sentence);
    }
});

it('solapa fragmentos consecutivos para no partir ideas', function (): void {
    $chunks = (new RecursiveChunker(new ChunkingOptions(300, 120)))->chunk([new TextSegment(paragraphs(3))]);

    expect(count($chunks))->toBeGreaterThan(1);
    for ($i = 1; $i < count($chunks); $i++) {
        preg_match_all('/Párrafo \d+ oración \d+/u', $chunks[$i - 1]->text, $previous);
        $lastSentenceOfPrevious = end($previous[0]);

        expect($chunks[$i]->text)->toContain($lastSentenceOfPrevious);
    }
});

it('prefiere cortar en fronteras de párrafo y conserva los saltos de línea', function (): void {
    $first = str_repeat('Primer párrafo con texto. ', 6);
    $second = str_repeat('Segundo párrafo distinto. ', 6);

    $chunks = (new RecursiveChunker(new ChunkingOptions(200, 0)))->chunk([new TextSegment("# Título\n{$first}\n\n{$second}")]);

    expect($chunks[0]->text)->toStartWith("# Título\nPrimer párrafo")
        ->and($chunks[0]->text)->not->toContain('Segundo');
});

it('corta palabras larguísimas sin separadores', function (): void {
    $chunks = (new RecursiveChunker(new ChunkingOptions(100, 0)))->chunk([new TextSegment(str_repeat('x', 250))]);

    expect($chunks)->toHaveCount(3);
});

it('conserva la página de origen y no cruza páginas', function (): void {
    $chunks = (new RecursiveChunker)->chunk([
        new TextSegment('Contenido de la página uno.', 1),
        new TextSegment('   ', 2),
        new TextSegment('Contenido de la página tres.', 3),
    ]);

    expect(array_map(static fn (TextSegment $c): ?int => $c->page, $chunks))->toBe([1, 3]);
});

it('valida las opciones', function (): void {
    expect(fn () => new ChunkingOptions(maxCharacters: 50))->toThrow(InvalidArgument::class)
        ->and(fn () => new ChunkingOptions(200, 200))->toThrow(InvalidArgument::class);
});
