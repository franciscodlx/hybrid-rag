<?php

declare(strict_types=1);

namespace HybridRag\Domain\Document;

interface DocumentRepository
{
    /**
     * @param  list<Chunk>  $chunks
     */
    public function save(Document $document, array $chunks): void;

    /**
     * @throws DocumentNotFound
     */
    public function get(CollectionId $collection, DocumentId $id): Document;

    public function findByChecksum(CollectionId $collection, Checksum $checksum): ?Document;

    /**
     * @return list<Document>
     */
    public function all(CollectionId $collection): array;

    public function delete(DocumentId $id): void;

    /**
     * Hidrata fragmentos por id conservando solo los que existen.
     *
     * @param  list<ChunkId>  $ids
     * @return array<string, ChunkWithSource> indexado por id de fragmento
     */
    public function chunksWithSource(array $ids): array;
}
