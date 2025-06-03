<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\TermAndConController;
use App\Http\Controllers\API\DemoRequestController;
use App\Http\Controllers\API\Events\EventController;
use App\Http\Controllers\API\Events\EventTncController;
use App\Http\Controllers\API\Auth\NewPasswordController;
use App\Http\Controllers\API\General\CarouselController;
use App\Http\Controllers\API\Documents\DocumentController;
use App\Http\Controllers\API\Facilities\FacilityController;
use App\Http\Controllers\API\Events\EventOrganizerController;
use App\Http\Controllers\API\Auth\PasswordResetLinkController;


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


Route::get('test', function () {
    dd('hello');
});

// Route::group(['middleware' => 'cors'], function () {
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
Route::prefix('carousels')
    ->name('carousels.')
    ->group(function () {
        Route::get('/', [CarouselController::class, 'index'])->name('index');
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

Route::prefix('event-organizers')->middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['role:eo-owner'])->group(function () {
        Route::post('/create', [EventOrganizerController::class, 'store']);
        Route::put('/edit/{id}', [EventOrganizerController::class, 'update']);
        Route::delete('/{id}', [EventOrganizerController::class, 'destroy']);
    });
    Route::middleware(['role:super-admin'])->group(function () {
        Route::get('/', [EventOrganizerController::class, 'index']);
        Route::get('/{id}', [EventOrganizerController::class, 'show']);
    });
});

Route::prefix('documents')
    ->name('document.')
    ->middleware(['auth:sanctum'])
    ->group(function () {
        Route::middleware(['role:eo-owner'])->group(function () {
            Route::post('/create', [DocumentController::class, 'store'])->name('store');
        });
        Route::middleware(['role:super-admin'])->group(function () {
            Route::get('/', [DocumentController::class, 'index'])->name('index');
            Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
            Route::patch('/{document}/status', [DocumentController::class, 'updateStatus'])->name('updateStatus');
        });
    });

Route::prefix('tnc-events')
    ->name('tnc-event.')
    ->middleware(['auth:sanctum', 'role:eo-owner'])
    ->group(function () {
        Route::get('/', [EventTncController::class, 'show'])->name('show');
        Route::post('/accept', [EventTncController::class, 'agree'])->name('accept');
    });

Route::prefix('events')
    ->name('event.')
    ->middleware(['auth:sanctum', 'role:eo-owner'])
    ->group(function () {
        Route::post('/create', [EventController::class, 'store'])->name('create');
        Route::put('/update/{id}', [EventController::class, 'update'])->name('update');
        Route::delete('/{id}', [EventController::class, 'destroy'])->name('destroy');
        Route::post('/{id}/publish', [EventController::class, 'publish']);
    });

Route::prefix('facilities')
    ->name('facility.')
    ->middleware(['auth:sanctum', 'role:super-admin|eo-owner'])
    ->group(function () {
        Route::get('/', [FacilityController::class, 'index'])->name('index');
        Route::post('/store', [FacilityController::class, 'store'])->name('store');
        Route::put('/update/{id}', [FacilityController::class, 'update'])->name('update');
        Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('destroy');
    });

Route::prefix('tnc')
    ->name('tnc.')
    ->middleware(['auth:sanctum', 'role:super-admin'])
    ->group(function () {
        Route::get('/', [TermAndConController::class, 'index'])->name('index');
        Route::get('/{type}/latest', [TermAndConController::class, 'latestByType'])->name('latest');
        Route::post('/', [TermAndConController::class, 'store'])->name('store');
        Route::put('/{id}', [TermAndConController::class, 'update'])->name('update');
        Route::delete('/{id}', [TermAndConController::class, 'destroy'])->name('destroy');
    });

//commit carousel api to git
Route::prefix('carousels')
    ->name('carousels.')
    ->middleware(['auth:sanctum', 'role:super-admin'])
    ->group(function () {
        Route::get('/all-carousel-list', [CarouselController::class, 'getCarouselList'])->name('get-carousel-list');
        Route::post('/', [CarouselController::class, 'store'])->name('store');
        Route::get('/{id}', [CarouselController::class, 'show'])->name('show');
        Route::put('/{id}', [CarouselController::class, 'update'])->name('update');
        Route::delete('/{id}', [CarouselController::class, 'destroy'])->name('destroy');
    });

// comment for push test from github direct to webhook
