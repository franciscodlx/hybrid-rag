<?php

declare(strict_types=1);

namespace HybridRag\Domain\Document;

/**
 * Fragmento junto con los datos mínimos de su documento, necesarios para
 * mostrar resultados y construir citas sin una segunda consulta.
 */
final readonly class ChunkWithSource
{
    public function __construct(
        public Chunk $chunk,
        public string $documentTitle,
    ) {}
}
