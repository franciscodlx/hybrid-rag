<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\AskRequest;
use App\Http\Resources\AnswerResource;
use HybridRag\Application\Answering\AskQuestion;
use HybridRag\Application\Answering\AskQuestionHandler;

final readonly class AskController
{
    public function __construct(private AskQuestionHandler $ask) {}

    public function __invoke(AskRequest $request, string $collection): AnswerResource
    {
        return new AnswerResource(($this->ask)(new AskQuestion(
            collection: $collection,
            question: $request->question(),
            evidenceLimit: $request->evidenceLimit(),
        )));
    }
}
