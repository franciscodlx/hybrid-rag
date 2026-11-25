<?php

declare(strict_types=1);

namespace Tests\Support;

use HybridRag\Domain\Embedding\Embedder;
use HybridRag\Domain\Embedding\Embedding;

/**
 * Decorador que cuenta llamadas: permite verificar que no se paga dos veces
 * por los mismos embeddings.
 */
final class CountingEmbedder implements Embedder
{
    public int $documentCalls = 0;

    public function __construct(private readonly Embedder $inner) {}

    public function dimensions(): int
    {
        return $this->inner->dimensions();
    }

    public function embedDocuments(array $texts): array
    {
        $this->documentCalls++;

        return $this->inner->embedDocuments($texts);
    }

    public function embedQuery(string $text): Embedding
    {
        return $this->inner->embedQuery($text);
    }
}
