<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Tests\Support\Corpus;

beforeEach(function (): void {
    $this->withToken('test-token');
});

function corpusFile(string $name = 'manual.txt'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, implode("\n\n", Corpus::TEXTS));
}

it('exige un token válido (fail-closed)', function (?string $token): void {
    $request = $token === null ? $this->withHeaders([]) : $this->withToken($token);
    if ($token === null) {
        $this->flushHeaders();
    }

    $request->getJson('/api/v1/collections/acme/documents')->assertUnauthorized();
})->with(['sin token' => [null], 'token incorrecto' => ['otro']]);

it('rechaza todo si no hay token configurado', function (): void {
    config(['rag.api_token' => '']);

    $this->getJson('/api/v1/collections/acme/documents')->assertUnauthorized();
});

it('indexa un documento y lo lista', function (): void {
    $id = $this->post('/api/v1/collections/acme/documents', ['file' => corpusFile(), 'title' => 'Manual interno'])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Manual interno')
        ->assertJsonPath('data.collection', 'acme')
        ->json('data.id');

    $this->getJson('/api/v1/collections/acme/documents')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $id);

    $this->getJson("/api/v1/collections/acme/documents/{$id}")->assertOk()->assertJsonPath('data.media_type', 'text/plain');
});

it('es idempotente: re-subir el mismo archivo devuelve 200 y el mismo id', function (): void {
    $first = $this->post('/api/v1/collections/acme/documents', ['file' => corpusFile()])->assertCreated()->json('data.id');

    $this->post('/api/v1/collections/acme/documents', ['file' => corpusFile('copia.txt')])
        ->assertOk()
        ->assertJsonPath('data.id', $first)
        ->assertJsonPath('data.title', 'manual');
});

it('valida archivo, tipo y colección', function (): void {
    $this->postJson('/api/v1/collections/acme/documents', [])->assertUnprocessable()->assertJsonValidationErrors('file');

    $this->post('/api/v1/collections/acme/documents', ['file' => UploadedFile::fake()->image('foto.png')], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file');

    $this->post('/api/v1/collections/NO_VALIDA/documents', ['file' => corpusFile()], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

it('borra un documento y luego responde 404', function (): void {
    $id = $this->post('/api/v1/collections/acme/documents', ['file' => corpusFile()])->json('data.id');

    $this->deleteJson("/api/v1/collections/otra/documents/{$id}")->assertNotFound();
    $this->deleteJson("/api/v1/collections/acme/documents/{$id}")->assertNoContent();
    $this->getJson("/api/v1/collections/acme/documents/{$id}")->assertNotFound();
});
