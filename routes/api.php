<?php

use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\DemoRequestController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\FacilityController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PasswordResetLinkController;
use App\Http\Controllers\API\NewPasswordController;
use Illuminate\Support\Facades\Mail;

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
// Route::get('/send-test-email', function () {
//     Mail::raw('This is a test email.', function ($message) {
//         $message->to('shabrinayusni21@gmail.com')
//                 ->subject('Test Email');
//     });

//     return 'Test email sent!';
// });


Route::get('test', function (){
    dd('hi');
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [PasswordResetLinkController::class, 'store']);
Route::post('/reset-password', [NewPasswordController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

Route::prefix('events')->name('event.')->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('index');
    Route::get('/{id}', [EventController::class, 'show'])->name('show');
});

Route::prefix('demo-requests')
    ->name('demo-request.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::post('/', [DemoRequestController::class, 'store'])->name('store');
        Route::get('/{id}/step', [DemoRequestController::class, 'getCurrentStep'])->name('current-step');
        Route::post('/{id}/pitching', [DemoRequestController::class, 'submitPitchingSchedule'])->name('submit-pitching-schedule');
        Route::post('/{id}/confirm-continuation', [DemoRequestController::class, 'confirmContinuation'])->name('confirm-continuation');

        Route::middleware(['role:super-admin'])->group(function () {
            Route::get('', [DemoRequestController::class, 'index'])->name('index');
            Route::put('/{id}', [DemoRequestController::class, 'update'])->name('update');
            Route::post('/{id}/approve-pitching', [DemoRequestController::class, 'approvePitching'])->name('approve-pitching');
            Route::post('/{id}/provide-demo', [DemoRequestController::class, 'provideDemoAccount'])->name('demo-account');
            Route::post('/{id}/upgrade', [DemoRequestController::class, 'upgradeToEoOwner'])->name('role-upgrade');
        });
    });
Route::prefix('events')
    ->name('event.')
    ->middleware(['auth:sanctum', 'role:eo-owner', 'check.demo.access'])
    ->group(function () {
        Route::post('/store', [EventController::class, 'store'])->name('store');
        Route::put('/update/{id}', [EventController::class, 'update'])->name('update');
        Route::delete('/{id}', [EventController::class, 'destroy'])->name('destroy');
    });

Route::prefix('facilities')
    ->name('facility.')
    ->middleware('auth:sanctum', 'role:super-admin|eo-owner', 'check.demo.access')
    ->group(function () {
        Route::get('/', [FacilityController::class, 'index'])->name('index');
        Route::post('/store', [FacilityController::class, 'store'])->name('store');
        Route::put('/update/{id}', [FacilityController::class, 'update'])->name('update');
        Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('destroy');
    });
