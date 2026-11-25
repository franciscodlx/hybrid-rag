<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\Database;

use HybridRag\Domain\Document\Checksum;
use HybridRag\Domain\Document\Chunk;
use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\ChunkWithSource;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\Document;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Document\DocumentNotFound;
use HybridRag\Domain\Document\DocumentRepository;
use Illuminate\Database\ConnectionInterface;

/**
 * Repositorio sobre el query builder (sin Eloquent): el dominio no hereda
 * de ningún modelo del framework y el mapeo queda explícito.
 */
final readonly class DatabaseDocumentRepository implements DocumentRepository
{
    private const int INSERT_BATCH = 500;

    public function __construct(private ConnectionInterface $db) {}

    public function save(Document $document, array $chunks): void
    {
        $this->db->table(Tables::DOCUMENTS)->insert([
            'id' => $document->id->value,
            'collection_id' => $document->collection->value,
            'title' => $document->title,
            'media_type' => $document->mediaType,
            'checksum' => $document->checksum->value,
            'chunk_count' => $document->chunkCount,
            'ingested_at' => $document->ingestedAt->format('Y-m-d H:i:s.u'),
        ]);

        $rows = array_map(static fn (Chunk $chunk): array => [
            'id' => $chunk->id->value,
            'document_id' => $chunk->documentId->value,
            'collection_id' => $chunk->collection->value,
            'position' => $chunk->position,
            'page' => $chunk->page,
            'text' => $chunk->text,
        ], $chunks);

        foreach (array_chunk($rows, self::INSERT_BATCH) as $batch) {
            $this->db->table(Tables::CHUNKS)->insert($batch);
        }
    }

    public function get(CollectionId $collection, DocumentId $id): Document
    {
        $row = $this->db->table(Tables::DOCUMENTS)
            ->where('id', $id->value)
            ->where('collection_id', $collection->value)
            ->first();

        return $row !== null ? $this->toDocument($row) : throw DocumentNotFound::withId($id);
    }

    public function findByChecksum(CollectionId $collection, Checksum $checksum): ?Document
    {
        $row = $this->db->table(Tables::DOCUMENTS)
            ->where('collection_id', $collection->value)
            ->where('checksum', $checksum->value)
            ->first();

        return $row !== null ? $this->toDocument($row) : null;
    }

    public function all(CollectionId $collection): array
    {
        $rows = $this->db->table(Tables::DOCUMENTS)
            ->where('collection_id', $collection->value)
            ->orderByDesc('ingested_at')
            ->get()
            ->all();

        return array_map($this->toDocument(...), array_values($rows));
    }

    public function delete(DocumentId $id): void
    {
        $this->db->table(Tables::CHUNKS)->where('document_id', $id->value)->delete();
        $this->db->table(Tables::DOCUMENTS)->where('id', $id->value)->delete();
    }

    public function chunksWithSource(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db->table(Tables::CHUNKS.' as c')
            ->join(Tables::DOCUMENTS.' as d', 'd.id', '=', 'c.document_id')
            ->whereIn('c.id', array_map(static fn (ChunkId $id): string => $id->value, $ids))
            ->get(['c.id', 'c.document_id', 'c.collection_id', 'c.position', 'c.page', 'c.text', 'd.title']);

        $found = [];
        foreach ($rows as $object) {
            $row = new Row($object);
            $chunk = new Chunk(
                id: ChunkId::fromString($row->string('id')),
                documentId: DocumentId::fromString($row->string('document_id')),
                collection: CollectionId::fromString($row->string('collection_id')),
                position: $row->int('position'),
                text: $row->string('text'),
                page: $row->nullableInt('page'),
            );
            $found[$chunk->id->value] = new ChunkWithSource($chunk, $row->string('title'));
        }

        return $found;
    }

    private function toDocument(object $object): Document
    {
        $row = new Row($object);

        return new Document(
            id: DocumentId::fromString($row->string('id')),
            collection: CollectionId::fromString($row->string('collection_id')),
            title: $row->string('title'),
            mediaType: $row->string('media_type'),
            checksum: Checksum::fromString($row->string('checksum')),
            chunkCount: $row->int('chunk_count'),
            ingestedAt: new \DateTimeImmutable($row->string('ingested_at'), new \DateTimeZone('UTC')),
        );
    }
}
