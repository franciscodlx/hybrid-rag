<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Persistence\InMemory;

use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Retrieval\Bm25;
use HybridRag\Domain\Retrieval\Hit;
use HybridRag\Domain\Retrieval\LexicalIndex;
use HybridRag\Domain\Retrieval\RankedList;
use HybridRag\Domain\Text\Analyzer;

/**
 * Índice invertido en memoria. Útil para tests y demos; es la implementación
 * de referencia contra la que se valida el índice SQL.
 */
final class InMemoryLexicalIndex implements LexicalIndex
{
    /** @var array<string, array{collection: string, document: string, length: int, tf: array<string, int>}> */
    private array $entries = [];

    public function __construct(
        private readonly Analyzer $analyzer,
        private readonly Bm25 $bm25 = new Bm25,
    ) {}

    public function index(array $chunks): void
    {
        foreach ($chunks as $chunk) {
            $tokens = $this->analyzer->tokens($chunk->text);
            $this->entries[$chunk->id->value] = [
                'collection' => $chunk->collection->value,
                'document' => $chunk->documentId->value,
                'length' => count($tokens),
                'tf' => array_count_values($tokens),
            ];
        }
    }

    public function remove(DocumentId $documentId): void
    {
        $this->entries = array_filter($this->entries, static fn (array $e): bool => $e['document'] !== $documentId->value);
    }

    public function search(CollectionId $collection, string $query, int $limit): RankedList
    {
        $terms = array_values(array_unique($this->analyzer->tokens($query)));
        $entries = array_filter($this->entries, static fn (array $e): bool => $e['collection'] === $collection->value);

        if ($terms === [] || $entries === []) {
            return RankedList::empty(self::NAME);
        }

        $documentCount = count($entries);
        $averageLength = array_sum(array_column($entries, 'length')) / $documentCount;

        $idf = [];
        foreach ($terms as $term) {
            $df = count(array_filter($entries, static fn (array $e): bool => isset($e['tf'][$term])));
            $idf[$term] = $this->bm25->idf($documentCount, $df);
        }

        $hits = [];
        foreach ($entries as $chunkId => $entry) {
            $score = 0.0;
            foreach ($terms as $term) {
                $score += $this->bm25->termScore($entry['tf'][$term] ?? 0, $entry['length'], $averageLength, $idf[$term]);
            }
            if ($score > 0.0) {
                $hits[] = new Hit(ChunkId::fromString((string) $chunkId), $score);
            }
        }

        return (new RankedList(self::NAME, $hits))->top($limit);
    }
}
