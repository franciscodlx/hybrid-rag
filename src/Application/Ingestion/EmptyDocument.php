<?php

declare(strict_types=1);

namespace HybridRag\Application\Ingestion;

use HybridRag\Domain\Shared\DomainException;

final class EmptyDocument extends DomainException
{
    public static function titled(string $title): self
    {
        return new self(sprintf('El documento "%s" no contiene texto extraíble.', $title));
    }
}
