<?php

declare(strict_types=1);

use HybridRag\Domain\Text\StandardAnalyzer;
use HybridRag\Infrastructure\Embedding\EmbeddingFailed;
use HybridRag\Infrastructure\Embedding\HashingEmbedder;
use HybridRag\Infrastructure\Embedding\VoyageEmbedder;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;

it('envía input_type distinto para documentos y consultas, en lotes y en orden', function (): void {
    $http = new Factory;
    $http->fake(function (Request $request) use (&$calls) {
        $calls[] = $request->data();
        $inputs = $request->data()['input'];

        // Respuesta desordenada a propósito: el adaptador debe ordenar por "index".
        $data = array_reverse(array_map(
            static fn (string $text, int $i): array => ['index' => $i, 'embedding' => [mb_strlen($text), 1.0]],
            $inputs,
            array_keys($inputs),
        ));

        return Factory::response(['data' => $data]);
    });
    $calls = [];

    $embedder = new VoyageEmbedder($http, 'key', 'voyage-3.5', dimensions: 2, batchSize: 2);
    $vectors = $embedder->embedDocuments(['a', 'bb', 'ccc']);
    $embedder->embedQuery('pregunta');

    expect($calls)->toHaveCount(3)
        ->and(array_column($calls, 'input_type'))->toBe(['document', 'document', 'query'])
        ->and(array_map(static fn ($v) => $v->vector[0], $vectors))->toBe([1.0, 2.0, 3.0]);
});

it('detecta vectores con dimensión distinta a la configurada', function (): void {
    $http = new Factory;
    $http->fake(['*' => Factory::response(['data' => [['index' => 0, 'embedding' => [0.1, 0.2, 0.3]]]])]);

    expect(fn () => (new VoyageEmbedder($http, 'key', 'voyage-3.5', dimensions: 1024))->embedQuery('x'))
        ->toThrow(EmbeddingFailed::class, '1024');
});

it('el embedder por hashing es determinista y normalizado', function (): void {
    $embedder = new HashingEmbedder(new StandardAnalyzer, 64);

    $a = $embedder->embedQuery('certificado TLS expirado');

    expect($a->vector)->toBe($embedder->embedQuery('certificado TLS expirado')->vector)
        ->and(array_sum(array_map(static fn (float $v): float => $v * $v, $a->vector)))->toEqualWithDelta(1.0, 1e-9)
        ->and($a->cosineSimilarity($embedder->embedQuery('certificados TLS')))
        ->toBeGreaterThan($a->cosineSimilarity($embedder->embedQuery('reembolso de viáticos')));
});
