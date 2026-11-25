<?php

declare(strict_types=1);

namespace HybridRag\Domain\Shared;

final class InvalidArgument extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
