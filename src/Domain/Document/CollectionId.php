<?php

declare(strict_types=1);

namespace HybridRag\Domain\Document;

use HybridRag\Domain\Shared\InvalidArgument;

/**
 * Colección = frontera de aislamiento. Toda búsqueda se limita a una sola
 * colección, de modo que los documentos de un espacio nunca se filtran a otro.
 */
final readonly class CollectionId implements \Stringable
{
    private const string PATTERN = '/^[a-z0-9][a-z0-9_-]{0,63}$/';

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidArgument::because(
                'La colección debe tener 1-64 caracteres: minúsculas, dígitos, "-" o "_".',
            );
        }

        return new self($value);
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
