<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval\Fusion;

use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Shared\InvalidArgument;

/**
 * Reciprocal Rank Fusion (Cormack, Clarke & Büttcher, SIGIR 2009):
 *
 *   RRF(d) = Σ_r  w_r / (k + rank_r(d))
 *
 * Solo usa posiciones, así que no hay que normalizar puntuaciones de escalas
 * incompatibles (BM25 no acotado vs. coseno en [-1, 1]). k amortigua el peso
 * de las primeras posiciones; 60 es el valor del artículo original.
 */
final readonly class ReciprocalRankFusion implements RankFusion
{
    /**
     * @param  array<string, float>  $weights  peso por recuperador (1.0 por defecto)
     */
    public function __construct(
        private int $k = 60,
        private array $weights = [],
    ) {
        if ($k < 1) {
            throw InvalidArgument::because('La constante k de RRF debe ser >= 1.');
        }
        foreach ($weights as $retriever => $weight) {
            if ($weight < 0) {
                throw InvalidArgument::because(sprintf('El peso de "%s" no puede ser negativo.', $retriever));
            }
        }
    }

    public function fuse(array $lists): array
    {
        /** @var array<string, array{chunk: ChunkId, score: float, ranks: array<string, int>}> $accumulator */
        $accumulator = [];

        foreach ($lists as $list) {
            $weight = $this->weights[$list->retriever] ?? 1.0;

            foreach ($list->ranked() as $rank => $hit) {
                $key = $hit->chunkId->value;
                $accumulator[$key] ??= ['chunk' => $hit->chunkId, 'score' => 0.0, 'ranks' => []];
                $accumulator[$key]['score'] += $weight / ($this->k + $rank);
                $accumulator[$key]['ranks'][$list->retriever] = $rank;
            }
        }

        $fused = array_map(
            static fn (array $entry): FusedHit => new FusedHit($entry['chunk'], $entry['score'], $entry['ranks']),
            array_values($accumulator),
        );

        // Desempate estable: a igual puntuación gana el que aparece en más listas.
        usort($fused, static fn (FusedHit $a, FusedHit $b): int => [$b->score, count($b->ranks)] <=> [$a->score, count($a->ranks)]);

        return $fused;
    }
}
