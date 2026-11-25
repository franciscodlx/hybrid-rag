<?php

declare(strict_types=1);

use HybridRag\Application\Answering\AskQuestion;
use HybridRag\Application\Answering\AskQuestionHandler;
use HybridRag\Domain\Answering\AnswerGenerator;
use HybridRag\Domain\Document\CollectionId;
use HybridRag\Domain\Document\DocumentNotFound;
use HybridRag\Domain\Shared\InvalidArgument;
use HybridRag\Domain\Text\StandardAnalyzer;
use HybridRag\Infrastructure\Generation\ExtractiveAnswerGenerator;
use Tests\Support\InMemoryRag;

it('encuentra coincidencias exactas de códigos (fortaleza de BM25)', function (): void {
    $rag = new InMemoryRag;
    $rag->ingestCorpus();

    $results = $rag->searcher->search(CollectionId::fromString('acme'), '¿Qué significa ERR-4521?', 3);

    expect($results[0]->chunk->text)->toContain('ERR-4521')
        ->and($results[0]->ranks)->toHaveKeys(['bm25', 'vector'])
        ->and($results[0]->documentTitle)->toBe('Manual interno');
});

it('aísla colecciones: nunca devuelve fragmentos de otra colección', function (): void {
    $rag = new InMemoryRag;
    $rag->ingestCorpus('acme');

    expect($rag->searcher->search(CollectionId::fromString('globex'), 'política de vacaciones', 5))->toBe([]);
});

it('deja de encontrar un documento tras borrarlo', function (): void {
    $rag = new InMemoryRag;
    $id = $rag->ingestCorpus();

    ($rag->delete)('acme', $id);

    expect($rag->searcher->search(CollectionId::fromString('acme'), 'vacaciones', 5))->toBe([])
        ->and(fn () => ($rag->delete)('acme', $id))->toThrow(DocumentNotFound::class);
});

it('no permite borrar un documento desde otra colección', function (): void {
    $rag = new InMemoryRag;
    $id = $rag->ingestCorpus('acme');

    expect(fn () => ($rag->delete)('globex', $id))->toThrow(DocumentNotFound::class);
});

it('valida la consulta y el límite', function (string $query, int $limit): void {
    $rag = new InMemoryRag;

    expect(fn () => $rag->searcher->search(CollectionId::fromString('acme'), $query, $limit))->toThrow(InvalidArgument::class);
})->with([
    'vacía' => ['   ', 5],
    'demasiado larga' => [str_repeat('a', 1001), 5],
    'límite 0' => ['hola', 0],
    'límite excesivo' => ['hola', 51],
]);

it('responde sin llamar al LLM cuando no hay evidencia', function (): void {
    $rag = new InMemoryRag;
    $generator = Mockery::mock(AnswerGenerator::class);
    $generator->shouldNotReceive('answer');

    $result = (new AskQuestionHandler($rag->searcher, $generator))(new AskQuestion('vacia', '¿Cuántos días de vacaciones?'));

    expect($result->answer->isGrounded())->toBeFalse()
        ->and($result->evidence)->toBe([]);
});

it('genera una respuesta citada con la evidencia recuperada', function (): void {
    $rag = new InMemoryRag;
    $rag->ingestCorpus();

    $result = (new AskQuestionHandler($rag->searcher, new ExtractiveAnswerGenerator(new StandardAnalyzer)))(
        new AskQuestion('acme', '¿Cuántos días de vacaciones otorga la política?'),
    );

    expect($result->answer->isGrounded())->toBeTrue()
        ->and($result->answer->text)->toContain('20 días hábiles')
        ->and($result->answer->text)->toContain('[1]')
        ->and($result->answer->citations[0]->documentTitle)->toBe('Manual interno');
});
