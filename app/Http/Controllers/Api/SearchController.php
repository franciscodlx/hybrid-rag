<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\SearchRequest;
use App\Http\Resources\RetrievedChunkResource;
use HybridRag\Application\Search\HybridSearcher;
use HybridRag\Domain\Document\CollectionId;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class SearchController
{
    public function __construct(private HybridSearcher $searcher) {}

    public function __invoke(SearchRequest $request, string $collection): AnonymousResourceCollection
    {
        return RetrievedChunkResource::collection($this->searcher->search(
            CollectionId::fromString($collection),
            $request->searchQuery(),
            $request->limit(),
        ));
    }
}
