<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\InMemory;

use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Embedding\Embedding;
use HybridRag\Domain\Retrieval\Hit;
use HybridRag\Domain\Retrieval\RankedList;
use HybridRag\Domain\Retrieval\VectorIndex;
use HybridRag\Domain\Shared\InvalidArgument;

/**
 * k-NN exacto por fuerza bruta (O(n)). Suficiente para tests y corpus
 * pequeños; en producción se usa HNSW de pgvector.
 */
final class InMemoryVectorIndex implements VectorIndex
{
    /** @var array<string, array{collection: string, document: string, embedding: Embedding}> */
    private array $entries = [];

    public function upsert(array $chunks, array $embeddings): void
    {
        if (count($chunks) !== count($embeddings)) {
            throw InvalidArgument::because('Debe haber exactamente un embedding por fragmento.');
        }

        foreach ($chunks as $i => $chunk) {
            $this->entries[$chunk->id->value] = [
                'collection' => $chunk->collection->value,
                'document' => $chunk->documentId->value,
                'embedding' => $embeddings[$i],
            ];
        }
    }

    public function remove(DocumentId $documentId): void
    {
        $this->entries = array_filter($this->entries, static fn (array $e): bool => $e['document'] !== $documentId->value);
    }

    public function search(CollectionId $collection, Embedding $query, int $limit): RankedList
    {
        $hits = [];
        foreach ($this->entries as $chunkId => $entry) {
            if ($entry['collection'] === $collection->value) {
                $hits[] = new Hit(ChunkId::fromString((string) $chunkId), $query->cosineSimilarity($entry['embedding']));
            }
        }

        return (new RankedList(self::NAME, $hits))->top($limit);
    }
}
