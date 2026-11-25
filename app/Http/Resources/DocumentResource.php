<?php

declare(strict_types=1);

namespace App\Http\Resources;

use HybridRag\Domain\Document\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Document $resource
 */
final class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id->value,
            'collection' => $this->resource->collection->value,
            'title' => $this->resource->title,
            'media_type' => $this->resource->mediaType,
            'checksum' => $this->resource->checksum->value,
            'chunk_count' => $this->resource->chunkCount,
            'ingested_at' => $this->resource->ingestedAt->format(DATE_ATOM),
        ];
    }
}
