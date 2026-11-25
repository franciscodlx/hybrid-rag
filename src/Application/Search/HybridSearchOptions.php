<?php

declare(strict_types=1);

namespace HybridRag\Application\Search;

use HybridRag\Domain\Shared\InvalidArgument;

final readonly class HybridSearchOptions
{
    /**
     * @param  int  $candidatesPerRetriever  cuántos candidatos aporta cada recuperador antes de fusionar
     * @param  int  $maxQueryLength  límite defensivo para consultas
     */
    public function __construct(
        public int $candidatesPerRetriever = 40,
        public int $maxQueryLength = 1000,
    ) {
        if ($candidatesPerRetriever < 1) {
            throw InvalidArgument::because('candidatesPerRetriever debe ser >= 1.');
        }
    }
}
