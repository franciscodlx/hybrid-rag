<?php

declare(strict_types=1);

namespace App\Console\Commands;

use HybridRag\Application\Ingestion\IngestDocument;
use HybridRag\Application\Ingestion\IngestDocumentHandler;
use HybridRag\Domain\Shared\DomainException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mime\MimeTypes;

#[Signature('rag:ingest {paths* : Archivos o carpetas a indexar} {--collection=default : Colección destino}')]
#[Description('Indexa documentos (txt, md, csv, pdf) en una colección')]
final class IngestCommand extends Command
{
    public function handle(IngestDocumentHandler $ingest): int
    {
        /** @var list<string> $paths */
        $paths = $this->argument('paths');
        $collection = (string) $this->option('collection');
        $failures = 0;

        foreach ($this->files($paths) as $file) {
            try {
                $result = $ingest(new IngestDocument(
                    collection: $collection,
                    title: pathinfo($file, PATHINFO_FILENAME),
                    content: (string) file_get_contents($file),
                    mediaType: $this->mediaType($file),
                ));
                $this->components->twoColumnDetail(
                    $file,
                    $result->created ? "<info>{$result->document->chunkCount} fragmentos</info>" : '<comment>ya indexado</comment>',
                );
            } catch (DomainException $e) {
                $failures++;
                $this->components->twoColumnDetail($file, "<error>{$e->getMessage()}</error>");
            }
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  list<string>  $paths
     * @return iterable<string>
     */
    private function files(array $paths): iterable
    {
        foreach ($paths as $path) {
            if (is_file($path)) {
                yield $path;
            } elseif (is_dir($path)) {
                $finder = Finder::create()->files()->in($path)->name(['*.txt', '*.md', '*.csv', '*.pdf'])->sortByName();
                foreach ($finder as $file) {
                    yield $file->getPathname();
                }
            } else {
                $this->components->warn("No existe: {$path}");
            }
        }
    }

    private function mediaType(string $file): string
    {
        return match (strtolower(pathinfo($file, PATHINFO_EXTENSION))) {
            'md', 'markdown' => 'text/markdown',
            default => MimeTypes::getDefault()->guessMimeType($file) ?? 'application/octet-stream',
        };
    }
}
