<?php

declare(strict_types=1);

namespace HybridRag\Domain\Retrieval;

use HybridRag\Domain\Document\Chunk;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;

/**
 * Índice invertido con ranking BM25: encuentra coincidencias EXACTAS de
 * términos (nombres propios, códigos, SKUs, siglas) que los embeddings suelen
 * difuminar.
 */
interface LexicalIndex
{
    public const string NAME = 'bm25';

    /**
     * @param  list<Chunk>  $chunks
     */
    public function index(array $chunks): void;

    public function remove(DocumentId $documentId): void;

    public function search(CollectionId $collection, string $query, int $limit): RankedList;
}
