<?php

declare(strict_types=1);

namespace HybridRag\Domain\Answering;

use HybridRag\Domain\Retrieval\RetrievedChunk;

/**
 * Ensambla una respuesta citada a partir de tramos de texto. Numera las
 * fuentes en orden de primera aparición ([1], [2], …) y agrupa citas
 * repetidas del mismo fragmento bajo un mismo número.
 */
final class AnswerComposer
{
    private string $text = '';

    /** @var array<string, array{number: int, source: RetrievedChunk, quotes: list<string>}> */
    private array $sources = [];

    /**
     * @param  list<array{source: RetrievedChunk, quote: string}>  $citedSpans
     */
    public function append(string $text, array $citedSpans = []): self
    {
        $markers = [];
        foreach ($citedSpans as $span) {
            $markers[] = $this->register($span['source'], $span['quote']);
        }

        if ($markers === []) {
            $this->text .= $text;

            return $this;
        }

        // El marcador va pegado al final del tramo citado, antes de su espacio final.
        $body = rtrim($text);
        $trailingWhitespace = substr($text, strlen($body));
        $unique = array_values(array_unique($markers));
        sort($unique);

        $this->text .= $body.' '.implode('', array_map(static fn (int $n): string => "[{$n}]", $unique)).$trailingWhitespace;

        return $this;
    }

    public function compose(string $generator): Answer
    {
        $citations = array_map(
            static fn (array $entry): Citation => new Citation(
                $entry['number'],
                $entry['source']->chunk->id,
                $entry['source']->chunk->documentId,
                $entry['source']->documentTitle,
                $entry['source']->chunk->page,
                $entry['quotes'],
            ),
            array_values($this->sources),
        );

        return new Answer(trim($this->text), $citations, $generator);
    }

    private function register(RetrievedChunk $source, string $quote): int
    {
        $key = $source->chunk->id->value;
        $this->sources[$key] ??= ['number' => count($this->sources) + 1, 'source' => $source, 'quotes' => []];

        $quote = trim($quote);
        if ($quote !== '' && ! in_array($quote, $this->sources[$key]['quotes'], true)) {
            $this->sources[$key]['quotes'][] = $quote;
        }

        return $this->sources[$key]['number'];
    }
}
