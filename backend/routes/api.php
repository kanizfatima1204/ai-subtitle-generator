<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SubtitleController;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/health-check', function () {
    $aiHealth = null;
    $aiError = null;
    try {
        $res = Http::timeout(5)->get('http://127.0.0.1:8001/health');
        $aiHealth = $res->json();
    } catch (\Throwable $e) {
        $aiError = $e->getMessage();
    }

    return response()->json([
        'status' => 'ok',
        'ai_service' => $aiHealth,
        'ai_error' => $aiError,
        'timestamp' => now()->toIso8601String(),
    ]);
});
Route::prefix('auth')->group(function () {
    Route::post(
        '/register',
        [AuthController::class, 'register']
    );

    Route::post(
        '/login',
        [AuthController::class, 'login']
    );
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get(
        '/subtitle-jobs',
        [SubtitleController::class, 'index']
    );

    Route::post(
        '/subtitle-jobs',
        [SubtitleController::class, 'store']
    );

    Route::get(
        '/subtitle-jobs/{subtitleJob}',
        [SubtitleController::class, 'show']
    );

    Route::get(
        '/subtitle-jobs/{subtitleJob}/preview',
        [SubtitleController::class, 'preview']
    );

    Route::get(
        '/subtitle-jobs/{subtitleJob}/media',
        [SubtitleController::class, 'media']
    );

    Route::put(
        '/subtitle-jobs/{subtitleJob}/subtitles',
        [SubtitleController::class, 'updateSubtitles']
    );

    Route::post(
        '/subtitle-jobs/{subtitleJob}/ai-assist',
        [SubtitleController::class, 'aiAssist']
    );

    Route::get(
        '/subtitle-jobs/{subtitleJob}/download',
        [SubtitleController::class, 'download']
    );

    Route::delete(
        '/subtitle-jobs/{subtitleJob}',
        [SubtitleController::class, 'destroy']
    );
});
