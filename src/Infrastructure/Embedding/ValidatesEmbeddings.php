<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Embedding;

use HybridRag\Domain\Embedding\Embedding;

trait ValidatesEmbeddings
{
    /**
     * @param  mixed  $vectors  respuesta cruda del proveedor
     * @return list<Embedding>
     */
    private function toEmbeddings(mixed $vectors, int $expectedCount, string $provider): array
    {
        if (! is_array($vectors) || count($vectors) !== $expectedCount) {
            throw EmbeddingFailed::unexpectedResponse($provider, sprintf('se esperaban %d vectores.', $expectedCount));
        }

        $embeddings = [];
        foreach ($vectors as $vector) {
            if (! is_array($vector) || ! array_is_list($vector)) {
                throw EmbeddingFailed::unexpectedResponse($provider, 'vector con formato inválido.');
            }
            /** @var list<float|int> $vector */
            $embedding = new Embedding($vector);
            if ($embedding->dimensions() !== $this->dimensions()) {
                throw EmbeddingFailed::dimensionMismatch($provider, $this->dimensions(), $embedding->dimensions());
            }
            $embeddings[] = $embedding;
        }

        return $embeddings;
    }
}
