<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Extraction;

use HybridRag\Application\Ingestion\TextExtractor;
use HybridRag\Application\Ingestion\UnsupportedMediaType;

/**
 * Delega en el primer extractor que soporte el tipo MIME (Open/Closed:
 * añadir DOCX u HTML es registrar un extractor más, sin tocar este código).
 */
final readonly class CompositeTextExtractor implements TextExtractor
{
    /** @var list<TextExtractor> */
    private array $extractors;

    public function __construct(TextExtractor ...$extractors)
    {
        $this->extractors = array_values($extractors);
    }

    public function supports(string $mediaType): bool
    {
        return $this->find(self::normalize($mediaType)) !== null;
    }

    public function extract(string $content, string $mediaType): array
    {
        $mediaType = self::normalize($mediaType);

        return ($this->find($mediaType) ?? throw UnsupportedMediaType::of($mediaType))->extract($content, $mediaType);
    }

    /**
     * "Text/Plain; charset=UTF-8" → "text/plain"
     */
    private static function normalize(string $mediaType): string
    {
        return strtolower(trim(explode(';', $mediaType)[0]));
    }

    private function find(string $mediaType): ?TextExtractor
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mediaType)) {
                return $extractor;
            }
        }

        return null;
    }
}
