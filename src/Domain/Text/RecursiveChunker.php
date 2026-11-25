<?php

declare(strict_types=1);

namespace HybridRag\Domain\Text;

/**
 * Fragmentador recursivo: intenta cortar por la frontera más "semántica"
 * disponible (párrafo → línea → oración → palabra) y solo baja de nivel cuando
 * una pieza sigue siendo demasiado grande. Después agrupa piezas contiguas
 * hasta el tamaño máximo, arrastrando un solape para no partir ideas en dos.
 *
 * Nunca cruza páginas: cada fragmento conserva su página para poder citarla.
 */
final readonly class RecursiveChunker
{
    /** @var list<non-empty-string> */
    private const array SEPARATORS = ["\n\n", "\n", '. ', '? ', '! ', '; ', ' '];

    public function __construct(private ChunkingOptions $options = new ChunkingOptions) {}

    /**
     * @param  list<TextSegment>  $pages
     * @return list<TextSegment>
     */
    public function chunk(array $pages): array
    {
        $chunks = [];
        foreach ($pages as $page) {
            foreach ($this->chunkText($page->text) as $text) {
                $chunks[] = new TextSegment($text, $page->page);
            }
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function chunkText(string $text): array
    {
        $text = $this->normalizeWhitespace($text);
        if ($text === '') {
            return [];
        }

        return $this->merge($this->split($text, self::SEPARATORS));
    }

    /**
     * Divide hasta que todas las piezas quepan en un fragmento. Cada pieza
     * conserva su separador final, de modo que unirlas reproduce el texto.
     *
     * @param  list<non-empty-string>  $separators
     * @return list<string>
     */
    private function split(string $text, array $separators): array
    {
        if (mb_strlen($text) <= $this->options->maxCharacters) {
            return [$text];
        }

        $separator = array_shift($separators);
        if ($separator === null) {
            return mb_str_split($text, max(1, $this->options->maxCharacters));
        }

        $pieces = [];
        foreach ($this->splitKeepingSeparator($text, $separator) as $piece) {
            array_push($pieces, ...$this->split($piece, $separators));
        }

        return $pieces;
    }

    /**
     * @param  non-empty-string  $separator
     * @return list<string>
     */
    private function splitKeepingSeparator(string $text, string $separator): array
    {
        $parts = explode($separator, $text);
        $last = array_key_last($parts);

        $pieces = [];
        foreach ($parts as $index => $part) {
            $piece = $index === $last ? $part : $part.$separator;
            if ($piece !== '') {
                $pieces[] = $piece;
            }
        }

        return $pieces;
    }

    /**
     * @param  list<string>  $pieces
     * @return list<string>
     */
    private function merge(array $pieces): array
    {
        $chunks = [];
        $window = [];
        $windowLength = 0;

        foreach ($pieces as $piece) {
            $length = mb_strlen($piece);
            if ($windowLength + $length > $this->options->maxCharacters && $window !== []) {
                $chunks[] = implode('', $window);
                [$window, $windowLength] = $this->overlapTail($window, $length);
            }
            $window[] = $piece;
            $windowLength += $length;
        }

        if ($window !== []) {
            $chunks[] = implode('', $window);
        }

        return array_values(array_filter(array_map(trim(...), $chunks), static fn (string $c): bool => $c !== ''));
    }

    /**
     * Conserva las últimas piezas (hasta el solape configurado) como contexto
     * inicial del siguiente fragmento, sin superar el tamaño máximo.
     *
     * @param  list<string>  $window
     * @return array{0: list<string>, 1: int}
     */
    private function overlapTail(array $window, int $incomingLength): array
    {
        $budget = min($this->options->overlapCharacters, $this->options->maxCharacters - $incomingLength);
        $tail = [];
        $length = 0;

        for ($i = count($window) - 1; $i >= 0; $i--) {
            $pieceLength = mb_strlen($window[$i]);
            if ($length + $pieceLength > $budget) {
                break;
            }
            array_unshift($tail, $window[$i]);
            $length += $pieceLength;
        }

        return [$tail, $length];
    }

    private function normalizeWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Solo espacios horizontales: en PCRE "\v" también incluye "\n" y destruiría los párrafos.
        $text = preg_replace('/[ \t\f\x{0B}]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
