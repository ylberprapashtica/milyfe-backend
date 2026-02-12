<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CaptureController;
use App\Http\Controllers\ProjectController;
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
    Route::apiResource('projects', ProjectController::class);
    Route::get('/captures/types', [CaptureController::class, 'getTypes']);
    Route::get('/captures/statuses', [CaptureController::class, 'getStatuses']);
    Route::get('/captures/tags', [CaptureController::class, 'getTags']);
    Route::get('/captures/search', [CaptureController::class, 'search']);
    Route::get('/captures/graph', [CaptureController::class, 'graph']);
    Route::get('/captures/{id}/links', [CaptureController::class, 'links']);
    Route::put('/captures/{id}/position', [CaptureController::class, 'updatePosition']);
    Route::put('/captures/{id}/project-position', [CaptureController::class, 'updateProjectPosition']);
    Route::post('/captures/links', [CaptureController::class, 'createLink']);
    Route::delete('/captures/links/{linkId}', [CaptureController::class, 'deleteLink']);
    Route::apiResource('captures', CaptureController::class);
});
