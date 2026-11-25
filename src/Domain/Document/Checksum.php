<?php

declare(strict_types=1);

namespace HybridRag\Domain\Document;

use HybridRag\Domain\Shared\InvalidArgument;

/**
 * Huella SHA-256 del contenido original; permite que la ingesta sea idempotente.
 */
final readonly class Checksum implements \Stringable
{
    private function __construct(public string $value) {}

    public static function of(string $content): self
    {
        return new self(hash('sha256', $content));
    }

    public static function fromString(string $value): self
    {
        if (preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw InvalidArgument::because('Checksum SHA-256 inválido.');
        }

        return new self($value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
