<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Web\AppointmentController;
use App\Http\Controllers\Web\QueueController;
use App\Http\Controllers\Web\ServiceController;
use Illuminate\Support\Facades\Route;

// ── Breeze default (keep these) ─────────────────────────────────
Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ── Public routes ────────────────────────────────────────────────
Route::get('/queue', [QueueController::class, 'index'])->name('queue.index');
Route::get('/queue/{service}', [QueueController::class, 'show'])->name('queue.show');
Route::get('/services', [ServiceController::class, 'index'])->name('services.index');
Route::get('/services/{service}', [ServiceController::class, 'show'])->name('services.show');

// ── Authenticated routes ─────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::resource('appointments', AppointmentController::class);

    Route::post('/queue/{service}/join', [QueueController::class, 'join'])->name('queue.join');
    Route::delete('/queue/{service}/leave', [QueueController::class, 'leave'])->name('queue.leave');
    Route::put('/queue/{service}/advance', [QueueController::class, 'advance'])->name('queue.advance');
    Route::put('/queue/entry/{entry}/served', [QueueController::class, 'markServed'])->name('queue.served');

    Route::resource('services', ServiceController::class)
         ->only(['create', 'store', 'edit', 'update', 'destroy']);
});

require __DIR__ . '/auth.php';