<?php

use App\Http\Controllers\Api\AiController;
use App\Http\Controllers\Api\ClientFeedController;
use App\Http\Controllers\Api\MediaController;
use App\Http\Controllers\Api\PortalController;
use App\Http\Controllers\TusController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/portal')->group(function () {
    Route::post('/search-token', [PortalController::class, 'searchToken'])->middleware('throttle:60,1');
    Route::get('/feed', [PortalController::class, 'feed'])->middleware('throttle:60,1');
    Route::get('/context', [PortalController::class, 'context'])->middleware('throttle:60,1');
    Route::get('/story/{publicId}', [PortalController::class, 'show'])->middleware('throttle:120,1');
});

Route::prefix('v1')->group(function () {
    Route::get('/feed', [ClientFeedController::class, 'index'])->middleware(['client.api:feed:read', 'throttle:60,1']);
    Route::get('/media/{id}/download', [MediaController::class, 'clientPresigned'])->middleware(['client.api:media:read', 'throttle:60,1']);
});

Route::middleware(['auth', 'throttle:60,1'])->group(function () {
    Route::post('/uploads', [TusController::class, 'create']);
    Route::patch('/uploads/{id}', [TusController::class, 'patch']);
    Route::match(['head'], '/uploads/{id}', [TusController::class, 'head']);
    Route::get('/media/{id}/presigned', [MediaController::class, 'presigned']);
});

Route::middleware('auth:sanctum')->post('/ai/{kind}', [AiController::class, 'assist'])->middleware('throttle:30,1');
