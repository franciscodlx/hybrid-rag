<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\Database;

use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Embedding\Embedding;
use HybridRag\Domain\Retrieval\Hit;
use HybridRag\Domain\Retrieval\RankedList;
use HybridRag\Domain\Retrieval\VectorIndex;
use HybridRag\Domain\Shared\InvalidArgument;
use Illuminate\Database\ConnectionInterface;

/**
 * k-NN aproximado con pgvector + HNSW (distancia coseno, operador <=>).
 *
 * El filtro por colección se combina con "iterative scan" (pgvector ≥ 0.8):
 * sin él, HNSW podría devolver menos de k filas cuando la colección es una
 * fracción pequeña del total.
 */
final readonly class PgVectorIndex implements VectorIndex
{
    private const int INSERT_BATCH = 200;

    public function __construct(
        private ConnectionInterface $db,
        private int $efSearch = 100,
    ) {}

    public function upsert(array $chunks, array $embeddings): void
    {
        if (count($chunks) !== count($embeddings)) {
            throw InvalidArgument::because('Debe haber exactamente un embedding por fragmento.');
        }

        $rows = [];
        foreach ($chunks as $i => $chunk) {
            $rows[] = [
                'chunk_id' => $chunk->id->value,
                'collection_id' => $chunk->collection->value,
                'embedding' => self::literal($embeddings[$i]),
            ];
        }

        foreach (array_chunk($rows, self::INSERT_BATCH) as $batch) {
            $this->db->table(Tables::EMBEDDINGS)->upsert($batch, ['chunk_id'], ['embedding', 'collection_id']);
        }
    }

    public function remove(DocumentId $documentId): void
    {
        $this->db->table(Tables::EMBEDDINGS)
            ->whereIn('chunk_id', $this->db->table(Tables::CHUNKS)->where('document_id', $documentId->value)->select('id'))
            ->delete();
    }

    public function search(CollectionId $collection, Embedding $query, int $limit): RankedList
    {
        $vector = self::literal($query);

        $rows = $this->db->transaction(function () use ($collection, $vector, $limit): array {
            // SET LOCAL: los ajustes solo viven dentro de esta transacción.
            $this->db->statement('SET LOCAL hnsw.iterative_scan = relaxed_order');
            $this->db->statement(sprintf('SET LOCAL hnsw.ef_search = %d', $this->efSearch));

            return $this->db->select(
                'SELECT chunk_id, 1 - (embedding <=> CAST(? AS vector)) AS score
                 FROM rag_embeddings
                 WHERE collection_id = ?
                 ORDER BY embedding <=> CAST(? AS vector)
                 LIMIT ?',
                [$vector, $collection->value, $vector, $limit],
            );
        });

        return new RankedList(self::NAME, array_map(self::toHit(...), array_values($rows)));
    }

    private static function toHit(object $object): Hit
    {
        $row = new Row($object);

        return new Hit(ChunkId::fromString($row->string('chunk_id')), $row->float('score'));
    }

    private static function literal(Embedding $embedding): string
    {
        return '['.implode(',', array_map(static fn (float $v): string => sprintf('%.8F', $v), $embedding->vector)).']';
    }
}
