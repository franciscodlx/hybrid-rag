<?php

declare(strict_types=1);

namespace HybridRag\Domain\Document;

use HybridRag\Domain\Shared\InvalidArgument;

final readonly class Document
{
    public function __construct(
        public DocumentId $id,
        public CollectionId $collection,
        public string $title,
        public string $mediaType,
        public Checksum $checksum,
        public int $chunkCount,
        public \DateTimeImmutable $ingestedAt,
    ) {
        if (trim($title) === '') {
            throw InvalidArgument::because('El título del documento no puede estar vacío.');
        }
        if ($chunkCount < 0) {
            throw InvalidArgument::because('El número de fragmentos no puede ser negativo.');
        }
    }
}
