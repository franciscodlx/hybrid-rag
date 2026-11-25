<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Extraction;

use HybridRag\Application\Ingestion\TextExtractor;
use HybridRag\Application\Ingestion\UnsupportedMediaType;
use HybridRag\Domain\Text\TextSegment;
use Smalot\PdfParser\Parser;

/**
 * Extrae texto página a página para que cada fragmento conserve su número de
 * página y las citas indiquen "p. N". PDFs escaneados (solo imagen) requieren
 * OCR previo.
 */
final readonly class PdfTextExtractor implements TextExtractor
{
    public function __construct(private Parser $parser = new Parser) {}

    public function supports(string $mediaType): bool
    {
        return $mediaType === 'application/pdf';
    }

    public function extract(string $content, string $mediaType): array
    {
        if (! $this->supports($mediaType)) {
            throw UnsupportedMediaType::of($mediaType);
        }

        $segments = [];
        foreach ($this->parser->parseContent($content)->getPages() as $index => $page) {
            $segments[] = new TextSegment($page->getText(), $index + 1);
        }

        return $segments;
    }
}
