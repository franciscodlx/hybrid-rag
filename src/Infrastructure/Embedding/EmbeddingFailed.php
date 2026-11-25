<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Embedding;

final class EmbeddingFailed extends \RuntimeException
{
    public static function unexpectedResponse(string $provider, string $detail): self
    {
        return new self(sprintf('Respuesta inesperada de %s: %s', $provider, $detail));
    }

    public static function dimensionMismatch(string $provider, int $expected, int $actual): self
    {
        return new self(sprintf(
            '%s devolvió vectores de %d dimensiones; la configuración (y la columna vector) espera %d.',
            $provider,
            $actual,
            $expected,
        ));
    }
}
