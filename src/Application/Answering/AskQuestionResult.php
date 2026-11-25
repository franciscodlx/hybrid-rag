<?php

declare(strict_types=1);

namespace HybridRag\Application\Answering;

use HybridRag\Domain\Answering\Answer;
use HybridRag\Domain\Retrieval\RetrievedChunk;

final readonly class AskQuestionResult
{
    /**
     * @param  list<RetrievedChunk>  $evidence
     */
    public function __construct(
        public Answer $answer,
        public array $evidence,
    ) {}
}
