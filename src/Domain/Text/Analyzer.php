<?php

declare(strict_types=1);

namespace HybridRag\Domain\Text;

/**
 * Convierte texto en términos indexables. Se usa EXACTAMENTE el mismo
 * analizador al indexar y al consultar; de lo contrario BM25 no encuentra nada.
 */
interface Analyzer
{
    /**
     * @return list<string>
     */
    public function tokens(string $text): array;
}
