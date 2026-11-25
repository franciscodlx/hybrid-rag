<?php

declare(strict_types=1);

namespace HybridRag\Domain\Document;

use HybridRag\Domain\Shared\DomainException;

final class DocumentNotFound extends DomainException
{
    public static function withId(DocumentId $id): self
    {
        return new self(sprintf('No existe el documento "%s".', $id));
    }
}
