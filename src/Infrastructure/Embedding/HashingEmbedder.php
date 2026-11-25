<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Embedding;

use HybridRag\Domain\Embedding\Embedder;
use HybridRag\Domain\Embedding\Embedding;
use HybridRag\Domain\Text\Analyzer;

/**
 * Embedder determinista SIN red ("feature hashing" de tokens y bigramas de
 * caracteres). NO es semántico: existe para tests, CI y demos offline. En
 * cualquier uso real configure Voyage u Ollama.
 */
final readonly class HashingEmbedder implements Embedder
{
    public function __construct(
        private Analyzer $analyzer,
        private int $dimensions = 256,
    ) {}

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embedDocuments(array $texts): array
    {
        return array_map($this->embed(...), $texts);
    }

    public function embedQuery(string $text): Embedding
    {
        return $this->embed($text);
    }

    private function embed(string $text): Embedding
    {
        $vector = array_fill(0, $this->dimensions, 0.0);

        foreach ($this->features($text) as $feature) {
            $hash = crc32($feature);
            $index = $hash % $this->dimensions;
            $vector[$index] += ($hash & 0x80000000) === 0 ? 1.0 : -1.0;
        }

        $norm = sqrt(array_sum(array_map(static fn (float $v): float => $v * $v, $vector)));
        if ($norm > 0.0) {
            $vector = array_map(static fn (float $v): float => $v / $norm, $vector);
        } else {
            $vector[0] = 1.0; // vector no nulo para que el coseno esté definido
        }

        return new Embedding(array_values($vector));
    }

    /**
     * @return list<string>
     */
    private function features(string $text): array
    {
        $features = [];
        foreach ($this->analyzer->tokens($text) as $token) {
            $features[] = 'w:'.$token;
            $padded = "^{$token}$";
            for ($i = 0, $n = mb_strlen($padded) - 2; $i < $n; $i++) {
                $features[] = 'c:'.mb_substr($padded, $i, 3);
            }
        }

        return $features;
    }
}
