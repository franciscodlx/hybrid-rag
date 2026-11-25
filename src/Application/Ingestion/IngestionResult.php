<?php

declare(strict_types=1);

namespace HybridRag\Application\Ingestion;

use HybridRag\Domain\Document\Document;

final readonly class IngestionResult
{
    private function __construct(
        public Document $document,
        public bool $created,
    ) {}

    public static function created(Document $document): self
    {
        return new self($document, true);
    }

    public static function alreadyIngested(Document $document): self
    {
        return new self($document, false);
    }
}
