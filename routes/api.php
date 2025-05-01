<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DemoRequestController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\FacilityController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

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

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.update');

Route::prefix('events')->name('event.')->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('index');
    Route::get('/{id}', [EventController::class, 'show'])->name('show');
});

Route::prefix('events')
    ->name('event.')
    ->middleware(['auth:sanctum', 'role:eo-owner'])
    ->group(function () {
        Route::post('/create', [EventController::class, 'store'])->name('create');
        Route::put('/edit/{id}', [EventController::class, 'update'])->name('edit');
        Route::delete('/{id}', [EventController::class, 'destroy'])->name('delete');
    });

Route::prefix('facilities')
    ->name('facility.')
    ->middleware('auth:sanctum', 'role:super-admin|eo-owner')
    ->group(function () {
        Route::get('/', [FacilityController::class, 'index'])->name('index');
        Route::post('/create', [FacilityController::class, 'store'])->name('create');
        Route::put('/edit/{id}', [FacilityController::class, 'update'])->name('edit');
        Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('delete');
    });

Route::middleware(['auth:sanctum', 'role:eo-owner'])->group(function () {
    Route::post('demo-reequests', [DemoRequestController::class, 'store'])->name('demo-request.create');
});

Route::middleware(['auth:sanctum', 'role:super-admin'])->group(function () {
    Route::get('demo-reequests', [DemoRequestController::class, 'index'])->name('demo-request.index');
    Route::put('demo-reequests/{id}', [DemoRequestController::class, 'update'])->name('demo-request.edit');
});
