<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreDocumentRequest;
use App\Http\Resources\DocumentResource;
use HybridRag\Application\Documents\DeleteDocumentHandler;
use HybridRag\Application\Ingestion\IngestDocument;
use HybridRag\Application\Ingestion\IngestDocumentHandler;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentId;
use HybridRag\Domain\Document\DocumentRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Controlador delgado: traduce HTTP ⇄ casos de uso, sin lógica de negocio.
 */
final readonly class DocumentController
{
    public function __construct(private DocumentRepository $documents) {}

    public function index(string $collection): AnonymousResourceCollection
    {
        return DocumentResource::collection($this->documents->all(CollectionId::fromString($collection)));
    }

    public function show(string $collection, string $document): DocumentResource
    {
        return new DocumentResource($this->documents->get(CollectionId::fromString($collection), DocumentId::fromString($document)));
    }

    public function store(StoreDocumentRequest $request, string $collection, IngestDocumentHandler $ingest): JsonResponse
    {
        $file = $request->document();

        $result = $ingest(new IngestDocument(
            collection: $collection,
            title: $request->title(),
            content: $file->getContent(),
            mediaType: $file->getMimeType() ?? 'application/octet-stream',
        ));

        return (new DocumentResource($result->document))
            ->response()
            ->setStatusCode($result->created ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    public function destroy(string $collection, string $document, DeleteDocumentHandler $delete): Response
    {
        $delete($collection, $document);

        return response()->noContent();
    }
}
