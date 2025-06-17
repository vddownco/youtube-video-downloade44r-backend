<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\VideoController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::middleware('throttle:60,1')->group(function () {
    // Video analysis and download routes
    Route::prefix('video')->group(function() {
        Route::post('/analyze', [VideoController::class, 'analyze']);
        Route::post('/download', [VideoController::class, 'download']);
        Route::get('/status/{downloadId}', [VideoController::class, 'status']);
        Route::get('/download/file/{downloadId}', [VideoController::class, 'downloadFile'])
            ->name('api.video.downloadfile');
        Route::get('/stream/{id}', [VideoController::class, 'streamFile'])
        ->name('api.video.stream-file');
        Route::get('/history', [VideoController::class, 'history']);
    });
});


// Health check route
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => '1.0.0'
    ]);
});

