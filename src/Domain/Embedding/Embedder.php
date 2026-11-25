<?php

declare(strict_types=1);

namespace HybridRag\Domain\Embedding;

/**
 * Los proveedores modernos (p. ej. Voyage) distinguen entre embeddings de
 * documento y de consulta; por eso son dos métodos distintos.
 */
interface Embedder
{
    public function dimensions(): int;

    /**
     * @param  list<string>  $texts
     * @return list<Embedding> alineado posición a posición con $texts
     */
    public function embedDocuments(array $texts): array;

    public function embedQuery(string $text): Embedding;
}
