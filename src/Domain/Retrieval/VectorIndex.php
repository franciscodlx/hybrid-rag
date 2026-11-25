<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval;

use HybridRag\Domain\Document\Chunk;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Embedding\Embedding;

/**
 * Búsqueda por similitud semántica (coseno): encuentra paráfrasis y
 * sinónimos que BM25 no ve.
 */
interface VectorIndex
{
    public const string NAME = 'vector';

    /**
     * @param  list<Chunk>  $chunks
     * @param  list<Embedding>  $embeddings  alineado posición a posición con $chunks
     */
    public function upsert(array $chunks, array $embeddings): void;

    public function remove(DocumentId $documentId): void;

    public function search(CollectionId $collection, Embedding $query, int $limit): RankedList;
}
