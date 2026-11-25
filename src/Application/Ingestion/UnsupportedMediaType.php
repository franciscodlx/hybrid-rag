<?php

declare(strict_types=1);

namespace HybridRag\Application\Ingestion;

use HybridRag\Domain\Shared\DomainException;

final class UnsupportedMediaType extends DomainException
{
    public static function of(string $mediaType): self
    {
        return new self(sprintf('Formato no soportado: "%s".', $mediaType));
    }
}
