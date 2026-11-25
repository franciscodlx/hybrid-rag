<?php

declare(strict_types=1);

namespace HybridRag\Application\Answering;

use HybridRag\Application\Search\HybridSearcher;
use HybridRag\Domain\Answering\Answer;
use HybridRag\Domain\Answering\AnswerGenerator;
use HybridRag\Domain\Document\CollectionId;

final readonly class AskQuestionHandler
{
    public function __construct(
        private HybridSearcher $searcher,
        private AnswerGenerator $generator,
    ) {}

    public function __invoke(AskQuestion $command): AskQuestionResult
    {
        $evidence = $this->searcher->search(
            CollectionId::fromString($command->collection),
            $command->question,
            $command->evidenceLimit,
        );

        if ($evidence === []) {
            return new AskQuestionResult(Answer::withoutEvidence(), []);
        }

        return new AskQuestionResult($this->generator->answer($command->question, $evidence), $evidence);
    }
}
