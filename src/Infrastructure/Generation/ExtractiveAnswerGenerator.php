<?php

declare(strict_types=1);

namespace HybridRag\Infrastructure\Generation;

use HybridRag\Domain\Answering\Answer;
use HybridRag\Domain\Answering\AnswerComposer;
use HybridRag\Domain\Answering\AnswerGenerator;
use HybridRag\Domain\Text\Analyzer;

/**
 * Generador extractivo sin LLM: devuelve las oraciones de la evidencia que más
 * términos comparten con la pregunta, cada una citada. Sirve para demos y CI
 * sin clave de API, y como degradación controlada.
 */
final readonly class ExtractiveAnswerGenerator implements AnswerGenerator
{
    public function __construct(
        private Analyzer $analyzer,
        private int $maxSentences = 3,
    ) {}

    public function answer(string $question, array $evidence): Answer
    {
        $questionTerms = array_flip($this->analyzer->tokens($question));

        $candidates = [];
        foreach ($evidence as $rank => $item) {
            foreach ($this->sentences($item->chunk->text) as $sentence) {
                $overlap = count(array_intersect_key(array_flip($this->analyzer->tokens($sentence)), $questionTerms));
                if ($overlap > 0) {
                    $candidates[] = ['source' => $item, 'sentence' => $sentence, 'overlap' => $overlap, 'rank' => $rank];
                }
            }
        }

        usort($candidates, static fn (array $a, array $b): int => [$b['overlap'], $a['rank']] <=> [$a['overlap'], $b['rank']]);

        $composer = new AnswerComposer;
        foreach (array_slice($candidates, 0, $this->maxSentences) as $candidate) {
            $composer->append($candidate['sentence'].' ', [['source' => $candidate['source'], 'quote' => $candidate['sentence']]]);
        }

        $answer = $composer->compose('extractive');

        return $answer->isGrounded() ? $answer : Answer::withoutEvidence();
    }

    /**
     * @return list<string>
     */
    private function sentences(string $text): array
    {
        $parts = preg_split('/(?<=[.!?])\s+|\n+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        // Descarta encabezados Markdown y fragmentos demasiado cortos para ser una respuesta.
        return array_values(array_filter(
            array_map(trim(...), $parts),
            static fn (string $s): bool => mb_strlen($s) > 20 && ! str_starts_with($s, '#'),
        ));
    }
}
