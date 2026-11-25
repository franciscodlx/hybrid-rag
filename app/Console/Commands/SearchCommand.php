<?php

declare(strict_types=1);

namespace App\Console\Commands;

use HybridRag\Application\Search\HybridSearcher;
use HybridRag\Domain\Document\CollectionId;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('rag:search {query} {--collection=default} {--limit=5}')]
#[Description('Búsqueda híbrida (BM25 + vectorial + RRF) mostrando el rango de cada recuperador')]
final class SearchCommand extends Command
{
    public function handle(HybridSearcher $searcher): int
    {
        $results = $searcher->search(
            CollectionId::fromString((string) $this->option('collection')),
            (string) $this->argument('query'),
            (int) $this->option('limit'),
        );

        $this->table(
            ['#', 'RRF', 'BM25', 'Vector', 'Documento', 'Fragmento'],
            array_map(static fn ($r, int $i): array => [
                $i + 1,
                number_format($r->score, 4),
                $r->ranks['bm25'] ?? '—',
                $r->ranks['vector'] ?? '—',
                $r->documentTitle.($r->chunk->page !== null ? " (p. {$r->chunk->page})" : ''),
                Str::limit(str_replace("\n", ' ', $r->chunk->text), 80),
            ], $results, array_keys($results)),
        );

        return self::SUCCESS;
    }
}
