<?php

declare(strict_types=1);

use Anthropic\Beta\Messages\BetaOutputConfig\Effort;
use Anthropic\Client;
use HybridRag\Domain\Answering\AnswerRefused;
use HybridRag\Domain\Retrieval\RetrievedChunk;
use HybridRag\Infrastructure\Generation\ClaudeAnswerGenerator;
use Tests\Support\Corpus;
use Tests\Support\RecordingTransporter;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function claudeMessage(array $content, array $overrides = []): array
{
    return [
        'id' => 'msg_test',
        'type' => 'message',
        'role' => 'assistant',
        'model' => 'claude-opus-5-5',
        'content' => $content,
        'stop_reason' => 'end_turn',
        'stop_sequence' => null,
        'stop_details' => null,
        'container' => null,
        'diagnostics' => null,
        'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ...$overrides,
    ];
}

/**
 * @return array{0: ClaudeAnswerGenerator, 1: RecordingTransporter}
 */
function claudeWith(array $responseBody): array
{
    $transporter = new RecordingTransporter($responseBody);
    $client = new Client(apiKey: 'sk-test', requestOptions: ['transporter' => $transporter, 'maxRetries' => 0]);

    return [new ClaudeAnswerGenerator($client, 'claude-opus-5-5', Effort::LOW), $transporter];
}

/**
 * @return list<RetrievedChunk>
 */
function evidence(): array
{
    [$vacations, $tls] = Corpus::chunks();

    return [
        new RetrievedChunk($vacations, 'Manual de RR. HH.', 0.03, ['bm25' => 1, 'vector' => 1]),
        new RetrievedChunk($tls, 'Runbook', 0.02, ['bm25' => 2]),
    ];
}

it('envía la evidencia como bloques search_result con citas activadas', function (): void {
    [$generator, $transporter] = claudeWith(claudeMessage([['type' => 'text', 'text' => 'Respuesta.', 'citations' => null]]));
    $evidence = evidence();

    $generator->answer('¿Cuántos días de vacaciones hay?', $evidence);

    $payload = $transporter->lastPayload();
    $content = $payload['messages'][0]['content'];

    expect($payload['model'])->toBe('claude-opus-5-5')
        ->and($payload['output_config'])->toBe(['effort' => 'low'])
        ->and($payload['fallbacks'])->toBe('default')
        ->and($transporter->requests[0]->getHeaderLine('anthropic-beta'))->toContain('server-side-fallback-2026-07-01')
        ->and($content)->toHaveCount(3)
        ->and($content[0])->toMatchArray([
            'type' => 'search_result',
            'source' => $evidence[0]->chunk->id->value,
            'title' => 'Manual de RR. HH.',
            'citations' => ['enabled' => true],
        ])
        ->and($content[0]['content'][0]['text'])->toBe($evidence[0]->chunk->text)
        ->and($content[2])->toBe(['type' => 'text', 'text' => 'Pregunta: ¿Cuántos días de vacaciones hay?']);
});

it('traduce las citas nativas a referencias numeradas', function (): void {
    $evidence = evidence();
    [$generator] = claudeWith(claudeMessage([
        ['type' => 'text', 'text' => 'Según la política, ', 'citations' => null],
        ['type' => 'text', 'text' => 'cada colaborador tiene 20 días hábiles al año.', 'citations' => [[
            'type' => 'search_result_location',
            'cited_text' => 'otorga 20 días hábiles al año',
            'search_result_index' => 0,
            'source' => $evidence[0]->chunk->id->value,
            'title' => 'Manual de RR. HH.',
            'start_block_index' => 0,
            'end_block_index' => 0,
        ]]],
    ]));

    $answer = $generator->answer('¿Vacaciones?', $evidence);

    expect($answer->text)->toBe('Según la política, cada colaborador tiene 20 días hábiles al año. [1]')
        ->and($answer->generator)->toBe('claude:claude-opus-5-5')
        ->and($answer->citations)->toHaveCount(1)
        ->and($answer->citations[0]->chunkId->equals($evidence[0]->chunk->id))->toBeTrue()
        ->and($answer->citations[0]->quotes)->toBe(['otorga 20 días hábiles al año']);
});

it('convierte una negativa del modelo en AnswerRefused', function (): void {
    [$generator] = claudeWith(claudeMessage([], [
        'stop_reason' => 'refusal',
        'stop_details' => [
            'type' => 'refusal',
            'category' => null,
            'explanation' => 'Contenido no permitido.',
            'fallback_credit_token' => null,
            'fallback_has_prefill_claim' => null,
        ],
    ]));

    expect(fn () => $generator->answer('¿?', evidence()))->toThrow(AnswerRefused::class, 'Contenido no permitido.');
});
