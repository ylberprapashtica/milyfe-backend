<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaptureController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Public authentication routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/captures/search', [CaptureController::class, 'search']);
    Route::get('/captures/graph', [CaptureController::class, 'graph']);
    Route::get('/captures/{id}/links', [CaptureController::class, 'links']);
    Route::put('/captures/{id}/position', [CaptureController::class, 'updatePosition']);
    Route::apiResource('captures', CaptureController::class);
});
