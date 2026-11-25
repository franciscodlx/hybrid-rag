<?php

declare(strict_types=1);

use HybridRag\Domain\Answering\AnswerComposer;
use HybridRag\Domain\Retrieval\RetrievedChunk;
use Tests\Support\Corpus;

it('numera fuentes por orden de aparición y agrupa citas del mismo fragmento', function (): void {
    [$first, $second] = Corpus::chunks();
    $a = new RetrievedChunk($first, 'Manual', 0.1, []);
    $b = new RetrievedChunk($second, 'Runbook', 0.1, []);

    $answer = (new AnswerComposer)
        ->append('Hay 20 días.', [['source' => $a, 'quote' => '20 días hábiles']])
        ->append(' El ERR-4521 es del TLS.', [['source' => $b, 'quote' => 'certificado TLS'], ['source' => $a, 'quote' => '20 días hábiles']])
        ->append(' Fin. ', [['source' => $b, 'quote' => 'TLS']])
        ->append('Cierre.')
        ->compose('test');

    expect($answer->text)->toBe('Hay 20 días. [1] El ERR-4521 es del TLS. [1][2] Fin. [2] Cierre.')
        ->and($answer->citations)->toHaveCount(2)
        ->and($answer->citations[0]->quotes)->toBe(['20 días hábiles'])
        ->and($answer->citations[1]->documentTitle)->toBe('Runbook')
        ->and($answer->citations[1]->quotes)->toBe(['certificado TLS', 'TLS'])
        ->and($answer->isGrounded())->toBeTrue();
});
