<?php

declare(strict_types=1);

namespace App\Console\Commands;

use HybridRag\Application\Answering\AskQuestion;
use HybridRag\Application\Answering\AskQuestionHandler;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rag:ask {question} {--collection=default} {--evidence=8}')]
#[Description('Responde una pregunta con citas a los documentos de la colección')]
final class AskCommand extends Command
{
    public function handle(AskQuestionHandler $ask): int
    {
        $result = $ask(new AskQuestion(
            collection: (string) $this->option('collection'),
            question: (string) $this->argument('question'),
            evidenceLimit: (int) $this->option('evidence'),
        ));

        $this->newLine();
        $this->line($result->answer->text);
        $this->newLine();

        foreach ($result->answer->citations as $citation) {
            $page = $citation->page !== null ? ", p. {$citation->page}" : '';
            $this->line("<info>[{$citation->number}]</info> {$citation->documentTitle}{$page}");
            foreach ($citation->quotes as $quote) {
                $this->line('    <comment>“'.str_replace("\n", ' ', $quote).'”</comment>');
            }
        }

        return self::SUCCESS;
    }
}
