<?php

declare(strict_types=1);

namespace HybridRag\Domain\Answering;

use HybridRag\Domain\Retrieval\RetrievedChunk;

interface AnswerGenerator
{
    /**
     * Genera una respuesta basada ÚNICAMENTE en la evidencia recibida.
     *
     * @param  non-empty-list<RetrievedChunk>  $evidence
     *
     * @throws AnswerRefused
     */
    public function answer(string $question, array $evidence): Answer;
}
