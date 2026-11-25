<?php

declare(strict_types=1);

namespace HybridRag\Domain\Shared;

use Ramsey\Uuid\Uuid as RamseyUuid;

/**
 * Identificador UUIDv7 (ordenable por tiempo, amigable con índices B-tree).
 */
abstract readonly class Uuid implements \Stringable
{
    final private function __construct(public string $value) {}

    public static function generate(): static
    {
        return new static(RamseyUuid::uuid7()->toString());
    }

    public static function fromString(string $value): static
    {
        if (! RamseyUuid::isValid($value)) {
            throw InvalidArgument::because(sprintf('"%s" no es un UUID válido.', $value));
        }

        return new static(strtolower($value));
    }

    public function equals(self $other): bool
    {
        return $other instanceof static && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
