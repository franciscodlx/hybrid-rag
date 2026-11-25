<?php

declare(strict_types=1);

use HybridRag\Domain\Text\StandardAnalyzer;

it('normaliza mayúsculas y acentos, y elimina stopwords', function (): void {
    expect((new StandardAnalyzer)->tokens('La POLÍTICA de Vacaciones del año'))
        ->toBe(['politica', 'vacaciones', 'año']); // la ñ se conserva: año ≠ ano
});

it('conserva códigos alfanuméricos como términos separados', function (): void {
    expect((new StandardAnalyzer)->tokens('Error ERR-4521 en TLS v1.3'))
        ->toBe(['error', 'err', '4521', 'tls', 'v1']);
});

it('ignora puntuación y tokens de un carácter', function (): void {
    expect((new StandardAnalyzer)->tokens('¡¿a, b... c?!'))->toBe([]);
});
