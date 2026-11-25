<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\InMemory;

use HybridRag\Domain\Document\Checksum;
use HybridRag\Domain\Document\Chunk;
use HybridRag\Domain\Document\ChunkWithSource;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\Document;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Document\DocumentNotFound;
use HybridRag\Domain\Document\DocumentRepository;

final class InMemoryDocumentRepository implements DocumentRepository
{
    /** @var array<string, Document> */
    private array $documents = [];

    /** @var array<string, Chunk> */
    private array $chunks = [];

    public function save(Document $document, array $chunks): void
    {
        $this->documents[$document->id->value] = $document;
        foreach ($chunks as $chunk) {
            $this->chunks[$chunk->id->value] = $chunk;
        }
    }

    public function get(CollectionId $collection, DocumentId $id): Document
    {
        $document = $this->documents[$id->value] ?? null;
        if ($document === null || ! $document->collection->equals($collection)) {
            throw DocumentNotFound::withId($id);
        }

        return $document;
    }

    public function findByChecksum(CollectionId $collection, Checksum $checksum): ?Document
    {
        foreach ($this->documents as $document) {
            if ($document->collection->equals($collection) && $document->checksum->value === $checksum->value) {
                return $document;
            }
        }

        return null;
    }

    public function all(CollectionId $collection): array
    {
        $documents = array_values(array_filter(
            $this->documents,
            static fn (Document $d): bool => $d->collection->equals($collection),
        ));
        usort($documents, static fn (Document $a, Document $b): int => $b->ingestedAt <=> $a->ingestedAt);

        return $documents;
    }

    public function delete(DocumentId $id): void
    {
        unset($this->documents[$id->value]);
        $this->chunks = array_filter($this->chunks, static fn (Chunk $c): bool => ! $c->documentId->equals($id));
    }

    public function chunksWithSource(array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            $chunk = $this->chunks[$id->value] ?? null;
            $document = $chunk !== null ? ($this->documents[$chunk->documentId->value] ?? null) : null;
            if ($chunk !== null && $document !== null) {
                $found[$id->value] = new ChunkWithSource($chunk, $document->title);
            }
        }

        return $found;
    }
}
