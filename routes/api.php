<?php

declare(strict_types=1);

use App\Http\Controllers\Api\AskController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Middleware\AuthenticateApiToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/collections/{collection}')
    ->middleware(AuthenticateApiToken::class)
    ->group(function (): void {
        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store']);
        Route::get('documents/{document}', [DocumentController::class, 'show']);
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);

        Route::post('search', SearchController::class)->middleware('throttle:120,1');
        // Cada pregunta cuesta tokens: límite más estricto.
        Route::post('ask', AskController::class)->middleware('throttle:30,1');
    });
