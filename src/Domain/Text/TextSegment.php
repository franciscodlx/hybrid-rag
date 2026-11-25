<?php

declare(strict_types=1);

namespace HybridRag\Domain\Text;

final readonly class TextSegment
{
    public function __construct(
        public string $text,
        public ?int $page = null,
    ) {}
}
