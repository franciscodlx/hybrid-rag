<?php

declare(strict_types=1);

namespace App\Providers;

use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Client as AnthropicClient;
use HybridRag\Application\Ingestion\TextExtractor;
use HybridRag\Application\Search\HybridSearchOptions;
use HybridRag\Application\Shared\TransactionManager;
use HybridRag\Domain\Answering\AnswerGenerator;
use HybridRag\Domain\Document\DocumentRepository;
use HybridRag\Domain\Embedding\Embedder;
use HybridRag\Domain\Retrieval\Bm25;
use HybridRag\Domain\Retrieval\Fusion\RankFusion;
use HybridRag\Domain\Retrieval\Fusion\ReciprocalRankFusion;
use HybridRag\Domain\Retrieval\LexicalIndex;
use HybridRag\Domain\Retrieval\VectorIndex;
use HybridRag\Domain\Text\Analyzer;
use HybridRag\Domain\Text\ChunkingOptions;
use HybridRag\Domain\Text\RecursiveChunker;
use HybridRag\Domain\Text\StandardAnalyzer;
use HybridRag\Infrastructure\Embedding\HashingEmbedder;
use HybridRag\Infrastructure\Embedding\OllamaEmbedder;
use HybridRag\Infrastructure\Embedding\VoyageEmbedder;
use HybridRag\Infrastructure\Extraction\CompositeTextExtractor;
use HybridRag\Infrastructure\Extraction\PdfTextExtractor;
use HybridRag\Infrastructure\Extraction\PlainTextExtractor;
use HybridRag\Infrastructure\Generation\ClaudeAnswerGenerator;
use HybridRag\Infrastructure\Generation\ExtractiveAnswerGenerator;
use HybridRag\Infrastructure\Persistence\Database\DatabaseDocumentRepository;
use HybridRag\Infrastructure\Persistence\Database\DatabaseTransactionManager;
use HybridRag\Infrastructure\Persistence\Database\PgVectorIndex;
use HybridRag\Infrastructure\Persistence\Database\SqlBm25Index;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryDocumentRepository;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryLexicalIndex;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryTransactionManager;
use HybridRag\Infrastructure\Persistence\InMemory\InMemoryVectorIndex;
use HybridRag\Infrastructure\SystemClock;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\ServiceProvider;
use Psr\Clock\ClockInterface;

/**
 * Composition root: el ÚNICO lugar donde se decide qué adaptador concreto
 * implementa cada puerto. El dominio y los casos de uso no conocen Laravel.
 */
final class RagServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->registerText();
        $this->registerStores();
        $this->registerEmbedder();
        $this->registerGenerator();
        $this->registerRetrieval();
    }

    private function registerText(): void
    {
        $this->app->singleton(Analyzer::class, StandardAnalyzer::class);
        $this->app->singleton(ClockInterface::class, SystemClock::class);

        $this->app->singleton(RecursiveChunker::class, fn (): RecursiveChunker => new RecursiveChunker(new ChunkingOptions(
            maxCharacters: $this->int('rag.chunking.max_characters'),
            overlapCharacters: $this->int('rag.chunking.overlap_characters'),
        )));

        $this->app->singleton(TextExtractor::class, fn (): TextExtractor => new CompositeTextExtractor(
            new PlainTextExtractor,
            new PdfTextExtractor,
        ));
    }

    private function registerStores(): void
    {
        $this->app->singleton(Bm25::class, fn (): Bm25 => new Bm25(
            k1: $this->float('rag.retrieval.bm25.k1'),
            b: $this->float('rag.retrieval.bm25.b'),
        ));

        $this->app->singleton(DocumentRepository::class, fn (Application $app): DocumentRepository => $this->documentsInMemory()
            ? new InMemoryDocumentRepository
            : new DatabaseDocumentRepository($app->make(ConnectionInterface::class)));

        $this->app->singleton(LexicalIndex::class, fn (Application $app): LexicalIndex => $this->documentsInMemory()
            ? new InMemoryLexicalIndex($app->make(Analyzer::class), $app->make(Bm25::class))
            : new SqlBm25Index($app->make(ConnectionInterface::class), $app->make(Analyzer::class), $app->make(Bm25::class)));

        $this->app->singleton(TransactionManager::class, fn (Application $app): TransactionManager => $this->documentsInMemory()
            ? new InMemoryTransactionManager
            : new DatabaseTransactionManager($app->make(ConnectionInterface::class)));

        $this->app->singleton(VectorIndex::class, fn (Application $app): VectorIndex => match (config('rag.stores.vector')) {
            'memory' => new InMemoryVectorIndex,
            'pgvector' => new PgVectorIndex($app->make(ConnectionInterface::class), $this->int('rag.retrieval.hnsw_ef_search')),
            default => throw new \InvalidArgumentException('RAG_VECTOR_STORE debe ser "pgvector" o "memory".'),
        });
    }

    private function registerEmbedder(): void
    {
        $this->app->singleton(Embedder::class, fn (Application $app): Embedder => match (config('rag.embeddings.driver')) {
            'voyage' => new VoyageEmbedder(
                http: $app->make(Http::class),
                apiKey: $this->requiredString('rag.embeddings.voyage.api_key', 'VOYAGE_API_KEY'),
                model: $this->string('rag.embeddings.voyage.model'),
                dimensions: $this->int('rag.embeddings.dimensions'),
                batchSize: $this->int('rag.embeddings.voyage.batch_size'),
            ),
            'ollama' => new OllamaEmbedder(
                http: $app->make(Http::class),
                baseUrl: $this->string('rag.embeddings.ollama.url'),
                model: $this->string('rag.embeddings.ollama.model'),
                dimensions: $this->int('rag.embeddings.dimensions'),
                documentPrefix: $this->string('rag.embeddings.ollama.document_prefix'),
                queryPrefix: $this->string('rag.embeddings.ollama.query_prefix'),
            ),
            'hashing' => new HashingEmbedder($app->make(Analyzer::class), $this->int('rag.embeddings.dimensions')),
            default => throw new \InvalidArgumentException('RAG_EMBEDDINGS debe ser "voyage", "ollama" o "hashing".'),
        });
    }

    private function registerGenerator(): void
    {
        $this->app->singleton(AnthropicClient::class, fn (): AnthropicClient => new AnthropicClient(
            apiKey: $this->requiredString('rag.generation.claude.api_key', 'ANTHROPIC_API_KEY'),
        ));

        $this->app->singleton(AnswerGenerator::class, fn (Application $app): AnswerGenerator => match (config('rag.generation.driver')) {
            'claude' => new ClaudeAnswerGenerator(
                client: $app->make(AnthropicClient::class),
                model: $this->string('rag.generation.claude.model'),
                effort: Effort::from($this->string('rag.generation.claude.effort')),
                maxTokens: $this->int('rag.generation.claude.max_tokens'),
                serverSideFallback: (bool) config('rag.generation.claude.server_side_fallback'),
            ),
            'extractive' => new ExtractiveAnswerGenerator($app->make(Analyzer::class)),
            default => throw new \InvalidArgumentException('RAG_GENERATOR debe ser "claude" o "extractive".'),
        });
    }

    private function registerRetrieval(): void
    {
        $this->app->singleton(RankFusion::class, fn (): RankFusion => new ReciprocalRankFusion(
            k: $this->int('rag.retrieval.rrf_k'),
            weights: [
                LexicalIndex::NAME => $this->float('rag.retrieval.weights.bm25'),
                VectorIndex::NAME => $this->float('rag.retrieval.weights.vector'),
            ],
        ));

        $this->app->singleton(HybridSearchOptions::class, fn (): HybridSearchOptions => new HybridSearchOptions(
            candidatesPerRetriever: $this->int('rag.retrieval.candidates_per_retriever'),
        ));
    }

    private function documentsInMemory(): bool
    {
        return match (config('rag.stores.documents')) {
            'memory' => true,
            'database' => false,
            default => throw new \InvalidArgumentException('RAG_STORE debe ser "database" o "memory".'),
        };
    }

    private function string(string $key): string
    {
        $value = config($key);

        return is_scalar($value) ? (string) $value : '';
    }

    private function requiredString(string $key, string $env): string
    {
        $value = $this->string($key);
        if ($value === '') {
            throw new \RuntimeException("Falta configurar {$env}.");
        }

        return $value;
    }

    private function int(string $key): int
    {
        $value = config($key);

        return is_numeric($value) ? (int) $value : throw new \InvalidArgumentException("{$key} debe ser numérico.");
    }

    private function float(string $key): float
    {
        $value = config($key);

        return is_numeric($value) ? (float) $value : throw new \InvalidArgumentException("{$key} debe ser numérico.");
    }
}
