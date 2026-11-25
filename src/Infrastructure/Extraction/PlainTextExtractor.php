<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Extraction;

use HybridRag\Application\Ingestion\TextExtractor;
use HybridRag\Application\Ingestion\UnsupportedMediaType;
use HybridRag\Domain\Text\TextSegment;

final class PlainTextExtractor implements TextExtractor
{
    private const array MEDIA_TYPES = ['text/plain', 'text/markdown', 'text/x-markdown', 'text/csv'];

    public function supports(string $mediaType): bool
    {
        return in_array($mediaType, self::MEDIA_TYPES, true);
    }

    public function extract(string $content, string $mediaType): array
    {
        if (! $this->supports($mediaType)) {
            throw UnsupportedMediaType::of($mediaType);
        }

        $text = mb_check_encoding($content, 'UTF-8') ? $content : mb_convert_encoding($content, 'UTF-8', 'ISO-8859-1');
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text; // BOM

        return [new TextSegment($text)];
    }
}
