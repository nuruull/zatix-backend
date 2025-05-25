<?php

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\EventController;
use App\Http\Controllers\API\EventTncController;
use App\Http\Controllers\API\FacilityController;
use App\Http\Controllers\API\TermAndConController;
use App\Http\Controllers\API\DemoRequestController;
use App\Http\Controllers\API\NewPasswordController;
use App\Http\Controllers\API\DocumentTypeController;
use App\Http\Controllers\API\UserAgreementController;
use App\Http\Controllers\API\EventOrganizerController;
use App\Http\Controllers\API\PasswordResetLinkController;
use App\Http\Controllers\API\IndividualDocumentController;
use App\Http\Controllers\API\OrganizationDocumentController;


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
    dd('hi');
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
    Route::post('/store', [EventController::class, 'store'])->name('store');
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
        Route::post('{eo_id}/document-type', [DocumentTypeController::class, 'store']);
        Route::post('document-type/{id}/individual', [IndividualDocumentController::class, 'store']);
        Route::get('document-type/{id}/individual', [IndividualDocumentController::class, 'show']);
        Route::post('document-type/{id}/organization', [OrganizationDocumentController::class, 'store']);
        Route::get('document-type/{id}/organization', [OrganizationDocumentController::class, 'show']);
    });
    Route::middleware(['role:super-admin'])->group(function () {
        Route::get('/', [EventOrganizerController::class, 'index']);
        Route::get('/{id}', [EventOrganizerController::class, 'show']);
    });
});


Route::prefix('tnc-events')
    ->name('tnc=event.')
    ->middleware(['auth:sanctum', 'role:eo-owner'])
    ->group(function () {
        Route::get('/', [EventTncController::class, 'show'])->name('show');
        Route::post('/accept', [EventTncController::class, 'agree'])->name('accept');
    });

Route::prefix('events')
    ->name('event.')
    ->middleware(['auth:sanctum', 'role:eo-owner'])
    ->group(function () {

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
// });

Route::prefix('documents')
    ->middleware(['auth:sanctum', 'role:super-admin'])
    ->group(function () {
        Route::prefix('individual')->group(function () {
            Route::get('/pending', [IndividualDocumentController::class, 'listPending']);
            Route::post('/{id}/verify', [IndividualDocumentController::class, 'verify']);
        });
        Route::prefix('organization')->group(function () {
            Route::get('/pending', [OrganizationDocumentController::class, 'listPending']);
            Route::post('/{id}/verify', [OrganizationDocumentController::class, 'verify']);
        });
    });

