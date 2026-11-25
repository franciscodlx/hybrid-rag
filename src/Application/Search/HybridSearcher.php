<?php

declare(strict_types=1);

namespace HybridRag\Application\Search;

use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentRepository;
use HybridRag\Domain\Embedding\Embedder;
use HybridRag\Domain\Retrieval\Fusion\FusedHit;
use HybridRag\Domain\Retrieval\Fusion\RankFusion;
use HybridRag\Domain\Retrieval\LexicalIndex;
use HybridRag\Domain\Retrieval\RetrievedChunk;
use HybridRag\Domain\Retrieval\VectorIndex;
use HybridRag\Domain\Shared\InvalidArgument;

/**
 * Núcleo del RAG híbrido:
 *
 *   consulta ─┬─▶ BM25 (léxico)      ─┐
 *             └─▶ embedding ─▶ k-NN ─┴─▶ RRF ─▶ top-k ─▶ hidratar fragmentos
 *
 * Cada recuperador devuelve un pool amplio de candidatos; la fusión premia
 * a los fragmentos en los que ambos coinciden.
 */
final readonly class HybridSearcher
{
    public function __construct(
        private LexicalIndex $lexicalIndex,
        private VectorIndex $vectorIndex,
        private Embedder $embedder,
        private RankFusion $fusion,
        private DocumentRepository $documents,
        private HybridSearchOptions $options = new HybridSearchOptions,
    ) {}

    /**
     * @return list<RetrievedChunk>
     */
    public function search(CollectionId $collection, string $query, int $limit): array
    {
        $query = trim($query);
        $this->guard($query, $limit);

        $pool = max($limit, $this->options->candidatesPerRetriever);

        $fused = $this->fusion->fuse([
            $this->lexicalIndex->search($collection, $query, $pool),
            $this->vectorIndex->search($collection, $this->embedder->embedQuery($query), $pool),
        ]);

        return $this->hydrate(array_slice($fused, 0, $limit));
    }

    /**
     * @param  list<FusedHit>  $hits
     * @return list<RetrievedChunk>
     */
    private function hydrate(array $hits): array
    {
        if ($hits === []) {
            return [];
        }

        $chunks = $this->documents->chunksWithSource(array_map(static fn (FusedHit $h) => $h->chunkId, $hits));

        $results = [];
        foreach ($hits as $hit) {
            $found = $chunks[$hit->chunkId->value] ?? null;
            if ($found !== null) {
                $results[] = new RetrievedChunk($found->chunk, $found->documentTitle, $hit->score, $hit->ranks);
            }
        }

        return $results;
    }

    private function guard(string $query, int $limit): void
    {
        if ($query === '') {
            throw InvalidArgument::because('La consulta no puede estar vacía.');
        }
        if (mb_strlen($query) > $this->options->maxQueryLength) {
            throw InvalidArgument::because(sprintf('La consulta supera %d caracteres.', $this->options->maxQueryLength));
        }
        if ($limit < 1 || $limit > 50) {
            throw InvalidArgument::because('El límite debe estar entre 1 y 50.');
        }
    }
}
