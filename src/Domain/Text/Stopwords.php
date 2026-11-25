<?php

declare(strict_types=1);

namespace HybridRag\Domain\Text;

/**
 * Palabras vacías ya plegadas (sin acentos), para que coincidan con la salida
 * de StandardAnalyzer.
 */
final class Stopwords
{
    public const array SPANISH_AND_ENGLISH = [
        // es
        'al', 'algo', 'como', 'con', 'cual', 'cuando', 'de', 'del', 'desde', 'donde', 'el', 'ella',
        'ellos', 'en', 'entre', 'era', 'es', 'esa', 'ese', 'eso', 'esta', 'este', 'esto', 'estos',
        'fue', 'ha', 'han', 'hay', 'la', 'las', 'le', 'les', 'lo', 'los', 'mas', 'me', 'mi', 'muy',
        'ni', 'no', 'nos', 'o', 'para', 'pero', 'por', 'que', 'se', 'ser', 'si', 'sin', 'sobre',
        'son', 'su', 'sus', 'tambien', 'te', 'tiene', 'un', 'una', 'uno', 'unos', 'unas', 'y', 'ya', 'yo',
        // en
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'by', 'for', 'from', 'has', 'have', 'he', 'in',
        'is', 'it', 'its', 'of', 'on', 'or', 'that', 'the', 'their', 'there', 'they', 'this', 'to',
        'was', 'were', 'what', 'when', 'where', 'which', 'who', 'will', 'with', 'you',
    ];

    private function __construct() {}
}
