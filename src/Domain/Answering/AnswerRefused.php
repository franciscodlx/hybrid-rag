<?php

declare(strict_types=1);

namespace HybridRag\Domain\Answering;

use HybridRag\Domain\Shared\DomainException;

final class AnswerRefused extends DomainException
{
    public static function because(string $reason): self
    {
        return new self('El modelo declinó responder: '.$reason);
    }
}
