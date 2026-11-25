<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Embedding;

use HybridRag\Domain\Embedding\Embedder;
use HybridRag\Domain\Embedding\Embedding;
use Illuminate\Http\Client\Factory as Http;

/**
 * Voyage AI: proveedor de embeddings recomendado por Anthropic. Distingue
 * input_type "document" y "query", lo que mejora la recuperación asimétrica
 * (preguntas cortas vs. pasajes largos).
 */
final readonly class VoyageEmbedder implements Embedder
{
    use ValidatesEmbeddings;

    private const string PROVIDER = 'Voyage AI';

    public function __construct(
        private Http $http,
        private string $apiKey,
        private string $model,
        private int $dimensions,
        private int $batchSize = 64,
        private string $baseUrl = 'https://api.voyageai.com/v1',
    ) {}

    public function dimensions(): int
    {
        return $this->dimensions;
    }

    public function embedDocuments(array $texts): array
    {
        $embeddings = [];
        foreach (array_chunk($texts, max(1, $this->batchSize)) as $batch) {
            array_push($embeddings, ...$this->embed($batch, 'document'));
        }

        return $embeddings;
    }

    public function embedQuery(string $text): Embedding
    {
        return $this->embed([$text], 'query')[0];
    }

    /**
     * @param  list<string>  $texts
     * @return list<Embedding>
     */
    private function embed(array $texts, string $inputType): array
    {
        $response = $this->http
            ->baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->timeout(60)
            ->retry(3, 500, throw: false)
            ->post('/embeddings', [
                'model' => $this->model,
                'input' => $texts,
                'input_type' => $inputType,
                'output_dimension' => $this->dimensions,
            ])
            ->throw();

        /** @var list<array{index: int, embedding: list<float>}> $data */
        $data = $response->json('data', []);
        usort($data, static fn (array $a, array $b): int => $a['index'] <=> $b['index']);

        return $this->toEmbeddings(array_column($data, 'embedding'), count($texts), self::PROVIDER);
    }
}
