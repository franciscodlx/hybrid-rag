<?php

declare(strict_types=1);

namespace HybridRag\Application\Ingestion;

use HybridRag\Application\Shared\TransactionManager;
use HybridRag\Domain\Document\Checksum;
use HybridRag\Domain\Document\Chunk;
use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\Document;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Document\DocumentRepository;
use HybridRag\Domain\Embedding\Embedder;
use HybridRag\Domain\Retrieval\LexicalIndex;
use HybridRag\Domain\Retrieval\VectorIndex;
use HybridRag\Domain\Text\RecursiveChunker;
use HybridRag\Domain\Text\TextSegment;
use Psr\Clock\ClockInterface;

/**
 * Caso de uso: extraer → fragmentar → vectorizar → persistir e indexar.
 *
 * Es idempotente por checksum: reenviar el mismo archivo a la misma
 * colección no duplica fragmentos ni vuelve a pagar embeddings.
 */
final readonly class IngestDocumentHandler
{
    public function __construct(
        private TextExtractor $extractor,
        private RecursiveChunker $chunker,
        private Embedder $embedder,
        private DocumentRepository $documents,
        private LexicalIndex $lexicalIndex,
        private VectorIndex $vectorIndex,
        private TransactionManager $transactions,
        private ClockInterface $clock,
    ) {}

    public function __invoke(IngestDocument $command): IngestionResult
    {
        $collection = CollectionId::fromString($command->collection);
        $checksum = Checksum::of($command->content);

        $existing = $this->documents->findByChecksum($collection, $checksum);
        if ($existing !== null) {
            return IngestionResult::alreadyIngested($existing);
        }

        $segments = $this->chunker->chunk($this->extractor->extract($command->content, $command->mediaType));
        if ($segments === []) {
            throw EmptyDocument::titled($command->title);
        }

        $document = new Document(
            id: DocumentId::generate(),
            collection: $collection,
            title: trim($command->title),
            mediaType: $command->mediaType,
            checksum: $checksum,
            chunkCount: count($segments),
            ingestedAt: $this->clock->now(),
        );
        $chunks = $this->toChunks($document, $segments);

        // La llamada externa (lenta y facturable) va FUERA de la transacción
        // para no mantener bloqueos abiertos mientras esperamos la red.
        $embeddings = $this->embedder->embedDocuments(array_map(static fn (Chunk $c): string => $c->text, $chunks));

        $this->transactions->transactional(function () use ($document, $chunks, $embeddings): void {
            $this->documents->save($document, $chunks);
            $this->lexicalIndex->index($chunks);
            $this->vectorIndex->upsert($chunks, $embeddings);
        });

        return IngestionResult::created($document);
    }

    /**
     * @param  list<TextSegment>  $segments
     * @return list<Chunk>
     */
    private function toChunks(Document $document, array $segments): array
    {
        return array_map(
            static fn (TextSegment $segment, int $position): Chunk => new Chunk(
                id: ChunkId::generate(),
                documentId: $document->id,
                collection: $document->collection,
                position: $position,
                text: $segment->text,
                page: $segment->page,
            ),
            $segments,
            array_keys($segments),
        );
    }
}
