<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval;

use HybridRag\Domain\Document\ChunkId;

final readonly class Hit
{
    public function __construct(
        public ChunkId $chunkId,
        public float $score,
    ) {}
}
