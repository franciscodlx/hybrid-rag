<?php

declare(strict_types=1);

namespace HybridRag\Domain\Document;

use HybridRag\Domain\Shared\InvalidArgument;

/**
 * Unidad mínima recuperable y citable. Conserva la página de origen para que
 * las citas puedan apuntar a una ubicación concreta del documento.
 */
final readonly class Chunk
{
    public function __construct(
        public ChunkId $id,
        public DocumentId $documentId,
        public CollectionId $collection,
        public int $position,
        public string $text,
        public ?int $page = null,
    ) {
        if ($position < 0) {
            throw InvalidArgument::because('La posición del fragmento no puede ser negativa.');
        }
        if (trim($text) === '') {
            throw InvalidArgument::because('Un fragmento no puede estar vacío.');
        }
        if ($page !== null && $page < 1) {
            throw InvalidArgument::because('Las páginas empiezan en 1.');
        }
    }
}
