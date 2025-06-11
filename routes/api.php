<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\TermAndConController;
use App\Http\Controllers\API\DemoRequestController;
use App\Http\Controllers\API\Events\EventController;
use App\Http\Controllers\API\Events\EventTncController;
use App\Http\Controllers\API\Log\ActivityLogController;
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


Route::prefix('events')->name('event.')->group(function () {
    Route::get('/', [EventController::class, 'index'])->name('index');
    Route::get('/{id}', [EventController::class, 'show'])->name('show');
});

Route::prefix('carousels')
    ->name('carousels.')
    ->group(function () {
        Route::get('/', [CarouselController::class, 'index'])->name('index');
    });

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('event-organizers')
        ->name('event-organizer.')
        ->group(function () {
            Route::middleware(['permission:create-event-organizer'])->post('/create', [EventOrganizerController::class, 'store'])->name('store');
            Route::middleware(['permission:update-event-organizer'])->put('/edit/{id}', [EventOrganizerController::class, 'update'])->name('update');
            Route::middleware(['permission:delete-event-organizer'])->delete('/{id}', [EventOrganizerController::class, 'destroy'])->name('destroy');
            Route::middleware(['permission:view-any-event-organizers'])->get('/', [EventOrganizerController::class, 'index'])->name('index');
            Route::middleware(['permission:view-event-organizer'])->get('/{id}', [EventOrganizerController::class, 'show'])->name('show');
        });

    Route::prefix('documents')
        ->name('document.')
        ->group(function () {
            Route::middleware(['permission:create-document'])->post('/create', [DocumentController::class, 'store'])->name('store');
            Route::middleware(['permission:view-any-documents'])->get('/', [DocumentController::class, 'index'])->name('index');
            Route::middleware(['permission:view-document'])->get('/{document}', [DocumentController::class, 'show'])->name('show');
            Route::middleware(['permission:update-document-status'])->patch('/{document}/status', [DocumentController::class, 'updateStatus'])->name('updateStatus');
        });

    Route::prefix('tnc-events')
        ->name('tnc-event.')
        ->group(function () {
            Route::middleware(['permission:view-tnc-event'])->get('/', [EventTncController::class, 'show'])->name('show');
            Route::middleware(['permission:accept-tnc-event'])->post('/accept', [EventTncController::class, 'agree'])->name('accept');
        });

    Route::prefix('events')
        ->name('event.')
        ->group(function () {
            Route::middleware(['permission:create-event'])->post('/create', [EventController::class, 'store'])->name('create');
            Route::middleware(['permission:update-event'])->put('/update/{id}', [EventController::class, 'update'])->name('update');
            Route::middleware(['permission:delete-event'])->delete('/{id}', [EventController::class, 'destroy'])->name('destroy');
            Route::middleware(['permission:publish-event'])->post('/{id}/publish', [EventController::class, 'publish']);

        });

    Route::prefix('facilities')
        ->name('facility.')
        ->middleware(['permission:view-any-facilities|create-facility|update-facility|delete-facility'])
        ->group(function () {
            Route::get('/', [FacilityController::class, 'index'])->name('index');
            Route::post('/store', [FacilityController::class, 'store'])->name('store');
            Route::put('/update/{id}', [FacilityController::class, 'update'])->name('update');
            Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('tnc')
        ->name('tnc.')
        ->group(function () {
            Route::middleware(['permission:view-any-tnc'])->get('/', [TermAndConController::class, 'index'])->name('index');
            Route::middleware(['permission:view-latest-tnc'])->get('/{type}/latest', [TermAndConController::class, 'latestByType'])->name('latest');
            Route::middleware(['permission:create-tnc'])->post('/', [TermAndConController::class, 'store'])->name('store');
            Route::middleware(['permission:update-tnc'])->put('/{id}', [TermAndConController::class, 'update'])->name('update');
            Route::middleware(['permission:delete-tnc'])->delete('/{id}', [TermAndConController::class, 'destroy'])->name('destroy');

        });

    //commit carousel api to git
    Route::prefix('carousels')
        ->name('carousels.')
        ->middleware(['permission:view-any-carousels|view-carousel|create-carousel|update-carousel|delete-carousel'])
        ->group(function () {
            Route::get('/all-carousel-list', [CarouselController::class, 'getCarouselList'])->name('get-carousel-list');
            Route::post('/', [CarouselController::class, 'store'])->name('store');
            Route::get('/{id}', [CarouselController::class, 'show'])->name('show');
            Route::put('/{id}', [CarouselController::class, 'update'])->name('update');
            Route::delete('/{id}', [CarouselController::class, 'destroy'])->name('destroy');
        });

        //create endpoint for activity log
    Route::middleware(['permission:view-activity-logs'])->get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');
});


