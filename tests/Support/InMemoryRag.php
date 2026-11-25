<?php

declare(strict_types=1);

namespace Tests\Support;

use HybridRag\Application\Documents\DeleteDocumentHandler;
use HybridRag\Application\Ingestion\IngestDocument;
use HybridRag\Application\Ingestion\IngestDocumentHandler;
use HybridRag\Application\Search\HybridSearcher;
use HybridRag\Domain\Retrieval\Fusion\ReciprocalRankFusion;
use HybridRag\Domain\Text\ChunkingOptions;
use HybridRag\Domain\Text\RecursiveChunker;
use HybridRag\Domain\Text\StandardAnalyzer;
use HybridRag\Infrastructure\Embedding\HashingEmbedder;
use HybridRag\Infrastructure\Extraction\CompositeTextExtractor;
use HybridRag\Infrastructure\Extraction\PlainTextExtractor;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryDocumentRepository;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryLexicalIndex;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryTransactionManager;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryVectorIndex;

/**
 * Ensamblado completo en memoria para tests de casos de uso (sin Laravel).
 */
final class InMemoryRag
{
    public readonly InMemoryDocumentRepository $documents;

    public readonly InMemoryLexicalIndex $lexical;

    public readonly InMemoryVectorIndex $vector;

    public readonly CountingEmbedder $embedder;

    public readonly IngestDocumentHandler $ingest;

    public readonly HybridSearcher $searcher;

    public readonly DeleteDocumentHandler $delete;

    public function __construct()
    {
        $analyzer = new StandardAnalyzer;
        $this->documents = new InMemoryDocumentRepository;
        $this->lexical = new InMemoryLexicalIndex($analyzer);
        $this->vector = new InMemoryVectorIndex;
        $this->embedder = new CountingEmbedder(new HashingEmbedder($analyzer, 256));
        $transactions = new InMemoryTransactionManager;

        $this->ingest = new IngestDocumentHandler(
            new CompositeTextExtractor(new PlainTextExtractor),
            new RecursiveChunker(new ChunkingOptions(maxCharacters: 200, overlapCharacters: 0)),
            $this->embedder,
            $this->documents,
            $this->lexical,
            $this->vector,
            $transactions,
            new FrozenClock,
        );
        $this->searcher = new HybridSearcher($this->lexical, $this->vector, $this->embedder, new ReciprocalRankFusion, $this->documents);
        $this->delete = new DeleteDocumentHandler($this->documents, $this->lexical, $this->vector, $transactions);
    }

    public function ingestCorpus(string $collection = 'acme', string $title = 'Manual interno'): string
    {
        return ($this->ingest)(new IngestDocument($collection, $title, implode("\n\n", Corpus::TEXTS), 'text/plain'))
            ->document->id->value;
    }
}
