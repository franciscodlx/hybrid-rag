<?php

declare(strict_types=1);

namespace HybridRag\Application\Ingestion;

final readonly class IngestDocument
{
    public function __construct(
        public string $collection,
        public string $title,
        public string $content,
        public string $mediaType,
    ) {}
}
