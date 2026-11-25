<?php

declare(strict_types=1);

use Anthropic\Core\Exceptions\APIException as AnthropicException;
use HybridRag\Application\Ingestion\UnsupportedMediaType;
use HybridRag\Domain\Document\DocumentNotFound;
use HybridRag\Domain\Shared\DomainException;
use HybridRag\Infrastructure\Embedding\EmbeddingFailed;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Errores de negocio → 4xx con un mensaje seguro de mostrar.
        $exceptions->render(fn (DomainException $e) => response()->json(
            ['message' => $e->getMessage()],
            match (true) {
                $e instanceof DocumentNotFound => Response::HTTP_NOT_FOUND,
                $e instanceof UnsupportedMediaType => Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                default => Response::HTTP_UNPROCESSABLE_ENTITY,
            },
        ));

        // Fallos de proveedores externos → 502 sin filtrar detalles internos.
        $exceptions->render(fn (AnthropicException|EmbeddingFailed|RequestException|ConnectionException $e) => response()->json(
            ['message' => 'Un proveedor externo (LLM o embeddings) no respondió correctamente. Intente de nuevo.'],
            Response::HTTP_BAD_GATEWAY,
        ));
    })->create();
