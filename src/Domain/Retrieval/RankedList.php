<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval;

/**
 * Salida de un recuperador: aciertos ordenados de mejor a peor. Las escalas
 * de puntuación de BM25 y coseno no son comparables; lo único que la fusión
 * usa es la POSICIÓN, por eso se expone el rango explícitamente.
 */
final readonly class RankedList
{
    /** @var list<Hit> */
    public array $hits;

    /**
     * @param  list<Hit>  $hits
     */
    public function __construct(
        public string $retriever,
        array $hits,
    ) {
        usort($hits, static fn (Hit $a, Hit $b): int => $b->score <=> $a->score);
        $this->hits = $hits;
    }

    public static function empty(string $retriever): self
    {
        return new self($retriever, []);
    }

    /**
     * @return iterable<int, Hit> rango (1..n) => acierto
     */
    public function ranked(): iterable
    {
        foreach ($this->hits as $index => $hit) {
            yield $index + 1 => $hit;
        }
    }

    public function top(int $limit): self
    {
        return new self($this->retriever, array_slice($this->hits, 0, max(0, $limit)));
    }

    public function isEmpty(): bool
    {
        return $this->hits === [];
    }
}
