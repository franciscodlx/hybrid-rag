<?php

declare(strict_types=1);

use HybridRag\Application\Ingestion\UnsupportedMediaType;
use HybridRag\Infrastructure\Extraction\CompositeTextExtractor;
use HybridRag\Infrastructure\Extraction\PdfTextExtractor;
use HybridRag\Infrastructure\Extraction\PlainTextExtractor;

it('delega por tipo MIME e ignora parámetros como charset', function (): void {
    $extractor = new CompositeTextExtractor(new PlainTextExtractor, new PdfTextExtractor);

    $segments = $extractor->extract("\xEF\xBB\xBFHola mundo", 'Text/Plain; charset=UTF-8');

    expect($segments)->toHaveCount(1)
        ->and($segments[0]->text)->toBe('Hola mundo')
        ->and($segments[0]->page)->toBeNull()
        ->and($extractor->supports('application/pdf'))->toBeTrue();
});

it('convierte Latin-1 a UTF-8', function (): void {
    $segments = (new PlainTextExtractor)->extract(mb_convert_encoding('política', 'ISO-8859-1', 'UTF-8'), 'text/plain');

    expect($segments[0]->text)->toBe('política');
});

it('rechaza formatos desconocidos', function (): void {
    expect(fn () => (new CompositeTextExtractor(new PlainTextExtractor))->extract('x', 'image/png'))
        ->toThrow(UnsupportedMediaType::class);
});
