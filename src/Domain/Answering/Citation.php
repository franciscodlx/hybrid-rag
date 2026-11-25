<?php

declare(strict_types=1);

namespace HybridRag\Domain\Answering;

use HybridRag\Domain\Document\ChunkId;
use HybridRag\Domain\Document\DocumentId;

/**
 * Referencia numerada [n] que liga una afirmación de la respuesta con el
 * fragmento exacto del documento privado que la respalda.
 */
final readonly class Citation
{
    /**
     * @param  list<string>  $quotes  pasajes literales citados por el modelo
     */
    public function __construct(
        public int $number,
        public ChunkId $chunkId,
        public DocumentId $documentId,
        public string $documentTitle,
        public ?int $page,
        public array $quotes,
    ) {}
}
