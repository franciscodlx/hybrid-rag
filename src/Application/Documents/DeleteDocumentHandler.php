<?php

declare(strict_types=1);

namespace HybridRag\Application\Documents;

use HybridRag\Application\Shared\TransactionManager;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Document\DocumentRepository;
use HybridRag\Domain\Retrieval\LexicalIndex;
use HybridRag\Domain\Retrieval\VectorIndex;

final readonly class DeleteDocumentHandler
{
    public function __construct(
        private DocumentRepository $documents,
        private LexicalIndex $lexicalIndex,
        private VectorIndex $vectorIndex,
        private TransactionManager $transactions,
    ) {}

    public function __invoke(string $collection, string $documentId): void
    {
        // get() verifica que el documento pertenece a la colección solicitada.
        $document = $this->documents->get(CollectionId::fromString($collection), DocumentId::fromString($documentId));

        $this->transactions->transactional(function () use ($document): void {
            $this->lexicalIndex->remove($document->id);
            $this->vectorIndex->remove($document->id);
            $this->documents->delete($document->id);
        });
    }
}
