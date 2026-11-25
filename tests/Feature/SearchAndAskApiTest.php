<?php

declare(strict_types=1);

use HybridRag\Domain\Answering\AnswerGenerator;
use HybridRag\Domain\Answering\AnswerRefused;
use Illuminate\Http\UploadedFile;
use Tests\Support\Corpus;

beforeEach(function (): void {
    // Fragmentos pequeños: una oración del corpus por fragmento.
    config(['rag.chunking.max_characters' => 150, 'rag.chunking.overlap_characters' => 0]);
    $this->withToken('test-token');
    $this->post('/api/v1/collections/acme/documents', [
        'file' => UploadedFile::fake()->createWithContent('manual.txt', implode("\n\n", Corpus::TEXTS)),
        'title' => 'Manual interno',
    ])->assertCreated();
});

it('busca de forma híbrida y explica el rango de cada recuperador', function (): void {
    $this->postJson('/api/v1/collections/acme/search', ['query' => 'ERR-4521', 'limit' => 3])
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.document_title', 'Manual interno')
        ->assertJsonPath('data.0.ranks.bm25', 1)
        ->assertJsonStructure(['data' => [['chunk_id', 'document_id', 'page', 'text', 'score', 'ranks']]]);

    expect($this->postJson('/api/v1/collections/acme/search', ['query' => 'ERR-4521'])->json('data.0.text'))
        ->toContain('ERR-4521');
});

it('responde con citas verificables', function (): void {
    $response = $this->postJson('/api/v1/collections/acme/ask', ['question' => '¿Cuántos días de vacaciones otorga la política?'])
        ->assertOk()
        ->assertJsonPath('data.grounded', true)
        ->assertJsonPath('data.generator', 'extractive')
        ->assertJsonPath('data.citations.0.number', 1)
        ->assertJsonPath('data.citations.0.document_title', 'Manual interno');

    $citedChunk = $response->json('data.citations.0.chunk_id');
    expect($response->json('data.answer'))->toContain('[1]')
        ->and(collect($response->json('data.evidence'))->pluck('chunk_id'))->toContain($citedChunk);
});

it('no inventa: sin evidencia en la colección lo dice explícitamente', function (): void {
    $this->postJson('/api/v1/collections/vacia/ask', ['question' => '¿Cuántos días de vacaciones?'])
        ->assertOk()
        ->assertJsonPath('data.grounded', false)
        ->assertJsonPath('data.citations', [])
        ->assertJsonPath('data.evidence', []);
});

it('traduce una negativa del modelo en 422', function (): void {
    $this->mock(AnswerGenerator::class)->shouldReceive('answer')->andThrow(AnswerRefused::because('política de uso'));

    $this->postJson('/api/v1/collections/acme/ask', ['question' => '¿Vacaciones?'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'El modelo declinó responder: política de uso');
});

it('valida la pregunta', function (): void {
    $this->postJson('/api/v1/collections/acme/ask', ['question' => str_repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('question');
});
