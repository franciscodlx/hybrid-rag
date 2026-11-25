<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autenticación por token Bearer, fail-closed: sin token configurado no se
 * sirve ningún documento. Comparación en tiempo constante (hash_equals).
 */
final class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('rag.api_token');
        $provided = $request->bearerToken();

        if (! is_string($expected) || $expected === '' || $provided === null || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'No autenticado.'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
