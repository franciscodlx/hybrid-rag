<?php

declare(strict_types=1);

/**
 * Reglas de dependencia (arquitectura hexagonal):
 *
 *   Domain  ←  Application  ←  Infrastructure  ←  App (Laravel)
 *
 * El núcleo no conoce el framework ni los proveedores externos.
 */
arch('el dominio es puro: sin framework, sin SDKs, sin capas externas')
    ->expect('HybridRag\Domain')
    ->toOnlyUse(['HybridRag\Domain', 'Ramsey\Uuid']);

arch('la aplicación solo depende del dominio y de PSR')
    ->expect('HybridRag\Application')
    ->toOnlyUse(['HybridRag\Domain', 'HybridRag\Application', 'Psr\Clock']);

arch('la infraestructura no depende de la capa de entrega')
    ->expect('HybridRag\Infrastructure')
    ->not->toUse('App');

arch('todo el código declara strict_types')
    ->expect(['HybridRag', 'App'])
    ->toUseStrictTypes();

arch('los objetos de valor y servicios del dominio son inmutables')
    ->expect('HybridRag\Domain')
    ->classes()
    ->toBeReadonly()
    ->ignoring([
        'HybridRag\Domain\Answering\AnswerComposer', // builder con estado local, no se comparte
        'HybridRag\Domain\Shared\DomainException',
        'HybridRag\Domain\Shared\InvalidArgument',
        'HybridRag\Domain\Document\DocumentNotFound',
        'HybridRag\Domain\Answering\AnswerRefused',
        'HybridRag\Domain\Text\Stopwords',
    ]);

arch('los controladores no acceden a la base de datos directamente')
    ->expect('App\Http\Controllers')
    ->not->toUse(['Illuminate\Support\Facades\DB', 'Illuminate\Database']);

arch('sin funciones de depuración olvidadas')
    ->preset()->php();

arch('buenas prácticas de seguridad')
    ->preset()->security()
    ->ignoring('HybridRag\Infrastructure\Embedding\HashingEmbedder'); // crc32 para hashing de features, no criptografía
