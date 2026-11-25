<?php

declare(strict_types=1);

namespace App\Http\Resources;

use HybridRag\Domain\Retrieval\RetrievedChunk;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property RetrievedChunk $resource
 */
final class RetrievedChunkResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'chunk_id' => $this->resource->chunk->id->value,
            'document_id' => $this->resource->chunk->documentId->value,
            'document_title' => $this->resource->documentTitle,
            'page' => $this->resource->chunk->page,
            'text' => $this->resource->chunk->text,
            'score' => round($this->resource->score, 6),
            // Explicabilidad: en qué posición lo puso cada recuperador.
            'ranks' => (object) $this->resource->ranks,
        ];
    }
}
