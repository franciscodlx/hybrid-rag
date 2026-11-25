<?php

declare(strict_types=1);

namespace HybridRag\Application\Ingestion;

use HybridRag\Domain\Text\TextSegment;

interface TextExtractor
{
    public function supports(string $mediaType): bool;

    /**
     * @return list<TextSegment> una entrada por página (o una sola si el formato no tiene páginas)
     *
     * @throws UnsupportedMediaType
     */
    public function extract(string $content, string $mediaType): array;
}
