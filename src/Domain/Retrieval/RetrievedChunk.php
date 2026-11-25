<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval;

use HybridRag\Domain\Document\Chunk;

/**
 * Fragmento final que devuelve la búsqueda híbrida y que se entrega al LLM
 * como evidencia.
 */
final readonly class RetrievedChunk
{
    /**
     * @param  array<string, int>  $ranks  posición en cada recuperador
     */
    public function __construct(
        public Chunk $chunk,
        public string $documentTitle,
        public float $score,
        public array $ranks,
    ) {}
}
