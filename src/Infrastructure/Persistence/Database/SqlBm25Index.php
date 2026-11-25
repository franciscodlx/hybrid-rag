<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\Database;

use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Retrieval\Bm25;
use HybridRag\Domain\Retrieval\Hit;
use HybridRag\Domain\Retrieval\LexicalIndex;
use HybridRag\Domain\Retrieval\RankedList;
use HybridRag\Domain\Text\Analyzer;
use Illuminate\Database\ConnectionInterface;

/**
 * BM25 real (no ts_rank) calculado en SQL sobre un índice invertido propio:
 *
 *   rag_lexical_docs     (chunk_id, collection_id, length)     → |D| y avgdl
 *   rag_lexical_postings (chunk_id, term, collection_id, tf)   → df y tf
 *
 * SQL portable (PostgreSQL y SQLite ≥ 3.35). La tokenización la hace el mismo
 * Analyzer del dominio, así que indexado y consulta son simétricos.
 *
 * Alternativa a escala: ParadeDB pg_search u OpenSearch detrás de la misma
 * interfaz LexicalIndex.
 */
final readonly class SqlBm25Index implements LexicalIndex
{
    private const int INSERT_BATCH = 1000;

    public function __construct(
        private ConnectionInterface $db,
        private Analyzer $analyzer,
        private Bm25 $bm25 = new Bm25,
    ) {}

    public function index(array $chunks): void
    {
        $docs = [];
        $postings = [];

        foreach ($chunks as $chunk) {
            $tokens = $this->analyzer->tokens($chunk->text);
            $docs[] = [
                'chunk_id' => $chunk->id->value,
                'collection_id' => $chunk->collection->value,
                'length' => count($tokens),
            ];
            foreach (array_count_values($tokens) as $term => $tf) {
                $postings[] = [
                    'chunk_id' => $chunk->id->value,
                    'term' => mb_substr((string) $term, 0, 128),
                    'collection_id' => $chunk->collection->value,
                    'tf' => $tf,
                ];
            }
        }

        foreach (array_chunk($docs, self::INSERT_BATCH) as $batch) {
            $this->db->table(Tables::LEXICAL_DOCS)->insert($batch);
        }
        foreach (array_chunk($postings, self::INSERT_BATCH) as $batch) {
            $this->db->table(Tables::LEXICAL_POSTINGS)->insert($batch);
        }
    }

    public function remove(DocumentId $documentId): void
    {
        $chunkIds = $this->db->table(Tables::CHUNKS)->where('document_id', $documentId->value)->select('id');

        $this->db->table(Tables::LEXICAL_POSTINGS)->whereIn('chunk_id', $chunkIds)->delete();
        $this->db->table(Tables::LEXICAL_DOCS)->whereIn('chunk_id', $chunkIds)->delete();
    }

    public function search(CollectionId $collection, string $query, int $limit): RankedList
    {
        $terms = array_values(array_unique($this->analyzer->tokens($query)));
        if ($terms === []) {
            return RankedList::empty(self::NAME);
        }

        $in = implode(', ', array_fill(0, count($terms), '?'));
        $docs = Tables::LEXICAL_DOCS;
        $postings = Tables::LEXICAL_POSTINGS;
        $sql = <<<SQL
            WITH stats AS (
                SELECT CAST(COUNT(*) AS DOUBLE PRECISION) AS n,
                       CAST(AVG(length) AS DOUBLE PRECISION) AS avgdl
                FROM {$docs}
                WHERE collection_id = ?
            ),
            df AS (
                SELECT term, CAST(COUNT(*) AS DOUBLE PRECISION) AS df
                FROM {$postings}
                WHERE collection_id = ? AND term IN ({$in})
                GROUP BY term
            )
            SELECT p.chunk_id,
                   SUM(
                       LN(1 + (s.n - df.df + 0.5) / (df.df + 0.5))
                       * (p.tf * (CAST(? AS DOUBLE PRECISION) + 1))
                       / (p.tf + CAST(? AS DOUBLE PRECISION)
                           * (1 - CAST(? AS DOUBLE PRECISION) + CAST(? AS DOUBLE PRECISION) * d.length / s.avgdl))
                   ) AS score
            FROM {$postings} p
            JOIN df ON df.term = p.term
            JOIN {$docs} d ON d.chunk_id = p.chunk_id
            CROSS JOIN stats s
            WHERE p.collection_id = ? AND p.term IN ({$in})
            GROUP BY p.chunk_id
            ORDER BY score DESC, p.chunk_id
            LIMIT ?
            SQL;

        $k1 = $this->bm25->k1;
        $b = $this->bm25->b;
        $bindings = [
            $collection->value,
            $collection->value, ...$terms,
            $k1, $k1, $b, $b,
            $collection->value, ...$terms,
            $limit,
        ];

        $hits = array_map(static function (object $object): Hit {
            $row = new Row($object);

            return new Hit(ChunkId::fromString($row->string('chunk_id')), $row->float('score'));
        }, array_values($this->db->select($sql, $bindings)));

        return new RankedList(self::NAME, $hits);
    }
}
