<?php

declare(strict_types=1);

namespace HybridRag\Domain\Text;

use HybridRag\Domain\Shared\InvalidArgument;

final readonly class ChunkingOptions
{
    public function __construct(
        public int $maxCharacters = 1200,
        public int $overlapCharacters = 150,
    ) {
        if ($maxCharacters < 100) {
            throw InvalidArgument::because('El tamaño máximo de fragmento debe ser de al menos 100 caracteres.');
        }
        if ($overlapCharacters < 0 || $overlapCharacters >= $maxCharacters) {
            throw InvalidArgument::because('El solape debe estar entre 0 y el tamaño máximo (exclusivo).');
        }
    }
}
