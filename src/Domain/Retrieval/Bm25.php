<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval;

use HybridRag\Domain\Shared\InvalidArgument;

/**
 * Okapi BM25 (variante de Lucene, IDF siempre positivo):
 *
 *   score(D, Q) = Σ_t  IDF(t) · tf·(k1 + 1) / (tf + k1·(1 − b + b·|D|/avgdl))
 *   IDF(t)      = ln(1 + (N − df + 0.5) / (df + 0.5))
 *
 * Esta clase es la definición canónica: el índice en memoria la usa
 * directamente y el índice SQL replica la misma fórmula, verificado por los
 * tests de contrato compartidos.
 */
final readonly class Bm25
{
    public function __construct(
        public float $k1 = 1.2,
        public float $b = 0.75,
    ) {
        if ($k1 < 0) {
            throw InvalidArgument::because('k1 debe ser >= 0.');
        }
        if ($b < 0 || $b > 1) {
            throw InvalidArgument::because('b debe estar en [0, 1].');
        }
    }

    public function idf(int $documentCount, int $documentFrequency): float
    {
        return log(1 + ($documentCount - $documentFrequency + 0.5) / ($documentFrequency + 0.5));
    }

    public function termScore(int $termFrequency, int $documentLength, float $averageLength, float $idf): float
    {
        if ($termFrequency === 0 || $averageLength <= 0.0) {
            return 0.0;
        }

        $norm = $this->k1 * (1 - $this->b + $this->b * $documentLength / $averageLength);

        return $idf * ($termFrequency * ($this->k1 + 1)) / ($termFrequency + $norm);
    }
}
