<?php

declare(strict_types=1);

namespace App\Http\Resources;

use HybridRag\Application\Answering\AskQuestionResult;
use HybridRag\Domain\Answering\Citation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property AskQuestionResult $resource
 */
final class AnswerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answer = $this->resource->answer;

        return [
            'answer' => $answer->text,
            'grounded' => $answer->isGrounded(),
            'generator' => $answer->generator,
            'citations' => array_map(static fn (Citation $c): array => [
                'number' => $c->number,
                'chunk_id' => $c->chunkId->value,
                'document_id' => $c->documentId->value,
                'document_title' => $c->documentTitle,
                'page' => $c->page,
                'quotes' => $c->quotes,
            ], $answer->citations),
            'evidence' => RetrievedChunkResource::collection($this->resource->evidence),
        ];
    }
}
