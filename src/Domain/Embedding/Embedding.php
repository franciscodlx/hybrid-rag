<?php

declare(strict_types=1);

namespace HybridRag\Domain\Embedding;

use HybridRag\Domain\Shared\InvalidArgument;

final readonly class Embedding
{
    /** @var list<float> */
    public array $vector;

    /**
     * @param  list<float|int>  $vector
     */
    public function __construct(array $vector)
    {
        if ($vector === []) {
            throw InvalidArgument::because('Un embedding no puede estar vacío.');
        }
        foreach ($vector as $value) {
            if (! is_finite((float) $value)) {
                throw InvalidArgument::because('Un embedding solo admite valores finitos.');
            }
        }

        $this->vector = array_map(floatval(...), array_values($vector));
    }

    public function dimensions(): int
    {
        return count($this->vector);
    }

    public function cosineSimilarity(self $other): float
    {
        if ($other->dimensions() !== $this->dimensions()) {
            throw InvalidArgument::because('No se pueden comparar embeddings de distinta dimensión.');
        }

        $dot = $normA = $normB = 0.0;
        foreach ($this->vector as $i => $a) {
            $b = $other->vector[$i];
            $dot += $a * $b;
            $normA += $a * $a;
            $normB += $b * $b;
        }

        return $normA === 0.0 || $normB === 0.0 ? 0.0 : $dot / (sqrt($normA) * sqrt($normB));
    }
}
