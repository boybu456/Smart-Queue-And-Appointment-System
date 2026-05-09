<?php

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\QueueController;
use App\Http\Controllers\Api\ServiceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Breeze default — keep this
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Your API routes ──────────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {

    Route::apiResource('services', ServiceController::class);
    Route::apiResource('appointments', AppointmentController::class);

    Route::get('/queue', [QueueController::class, 'index']);
    Route::get('/queue/{service}', [QueueController::class, 'show']);
    Route::post('/queue/{service}/join', [QueueController::class, 'join']);
    Route::delete('/queue/{service}/leave', [QueueController::class, 'leave']);
    Route::put('/queue/{service}/advance', [QueueController::class, 'advance']);
    Route::put('/queue/entry/{entry}/served', [QueueController::class, 'markServed']);
});

// Login — no auth required
Route::post('/v1/login', function (Request $request) {
    $credentials = $request->validate([
        'email'    => 'required|email',
        'password' => 'required',
    ]);

    if (! Auth::attempt($credentials)) {
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    $token = $request->user()->createToken('api-token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user'  => $request->user(),
    ]);
});

// Logout
Route::post('/v1/logout', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    return response()->json(['message' => 'Logged out.']);
})->middleware('auth:sanctum');