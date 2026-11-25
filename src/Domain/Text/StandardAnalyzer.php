<?php

declare(strict_types=1);

namespace HybridRag\Domain\Text;

/**
 * Analizador multilingüe (es/en) sin dependencias de extensiones:
 * minúsculas → plegado de acentos → tokenización Unicode → stopwords.
 *
 * Plegar acentos hace que "política" y "politica" coincidan, que es lo que
 * espera un usuario que escribe rápido.
 */
final readonly class StandardAnalyzer implements Analyzer
{
    private const array ACCENTS = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ç' => 'c',
    ];

    /** @var array<string, true> */
    private array $stopwords;

    /**
     * @param  list<string>|null  $stopwords  null = lista por defecto es+en
     */
    public function __construct(
        ?array $stopwords = null,
        private int $minTokenLength = 2,
    ) {
        $this->stopwords = array_fill_keys($stopwords ?? Stopwords::SPANISH_AND_ENGLISH, true);
    }

    public function tokens(string $text): array
    {
        $normalized = strtr(mb_strtolower($text, 'UTF-8'), self::ACCENTS);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if (mb_strlen($part, 'UTF-8') < $this->minTokenLength || isset($this->stopwords[$part])) {
                continue;
            }
            $tokens[] = $part;
        }

        return $tokens;
    }
}
