<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Embedding;

use HybridRag\Domain\Embedding\Embedder;
use HybridRag\Domain\Embedding\Embedding;
use Illuminate\Http\Client\Factory as Http;

/**
 * Embeddings 100 % locales: los documentos privados nunca salen de la máquina.
 * Los prefijos permiten usar modelos asimétricos como nomic-embed-text
 * ("search_document: " / "search_query: ").
 */
final readonly class OllamaEmbedder implements Embedder
{
    use ValidatesEmbeddings;

    private const string PROVIDER = 'Ollama';

    public function __construct(
        private Http $http,
        private string $baseUrl,
        private string $model,
        private int $dimensions,
        private string $documentPrefix = '',
        private string $queryPrefix = '',
        private int $batchSize = 32,
    ) {}

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embedDocuments(array $texts): array
    {
        $embeddings = [];
        foreach (array_chunk($texts, max(1, $this->batchSize)) as $batch) {
            array_push($embeddings, ...$this->embed(array_map(fn (string $t): string => $this->documentPrefix.$t, $batch)));
        }

        return $embeddings;
    }

    public function embedQuery(string $text): Embedding
    {
        return $this->embed([$this->queryPrefix.$text])[0];
    }

    /**
     * @param  list<string>  $texts
     * @return list<Embedding>
     */
    private function embed(array $texts): array
    {
        $response = $this->http
            ->baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(120)
            ->post('/api/embed', ['model' => $this->model, 'input' => $texts])
            ->throw();

        return $this->toEmbeddings($response->json('embeddings'), count($texts), self::PROVIDER);
    }
}
