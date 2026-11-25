<?php

declare(strict_types=1);

namespace HybridRag\Application\Answering;

final readonly class AskQuestion
{
    public function __construct(
        public string $collection,
        public string $question,
        public int $evidenceLimit = 8,
    ) {}
}
