<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval\Fusion;

use HybridRag\Domain\Document\ChunkId;

final readonly class FusedHit
{
    /**
     * @param  array<string, int>  $ranks  rango que obtuvo en cada recuperador (explicabilidad)
     */
    public function __construct(
        public ChunkId $chunkId,
        public float $score,
        public array $ranks,
    ) {}
}
