<?php

use App\Http\Controllers\API\Events\EventPublicController;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\TermAndConController;
use App\Http\Controllers\API\Events\StaffController;
use App\Http\Controllers\API\Events\EventController;
use App\Http\Controllers\API\Events\EventTncController;
use App\Http\Controllers\API\Log\ActivityLogController;
use App\Http\Controllers\API\Auth\NewPasswordController;
use App\Http\Controllers\API\General\CarouselController;
use App\Http\Controllers\API\Documents\DocumentController;
use App\Http\Controllers\API\Facilities\FacilityController;
use App\Http\Controllers\API\General\NotificationController;
use App\Http\Controllers\API\Events\EventOrganizerController;
use App\Http\Controllers\API\Auth\PasswordResetLinkController;
use Illuminate\Support\Facades\Auth;


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
Route::get('/get-general-tnc', [TermAndConController::class, 'getGeneralTnc']);


Route::prefix('events')->name('events.')->group(function () {
    Route::get('/', [EventPublicController::class, 'index'])->name('index');
    Route::get('/{event}', [EventPublicController::class, 'show'])->name('show');
});

Route::prefix('carousels')
    ->name('carousels.')
    ->group(function () {
        Route::get('/', [CarouselController::class, 'index'])->name('index');
    });

Route::get('/debug-permissions', function () {
    // Pastikan Anda mengirim token Bearer di header saat memanggil endpoint ini
    if (Auth::check()) {
        $user = Auth::user();
        return response()->json([
            'user_id' => $user->id,
            'name' => $user->name,
            'roles' => $user->getRoleNames(),
            'permissions_from_roles' => $user->getPermissionsViaRoles()->pluck('name'),
            'can_view_carousels' => $user->can('view-any-carousels')
        ]);
    } else {
        return response()->json(['message' => 'Not authenticated'], 401);
    }
});

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::prefix('event-organizers')
        ->name('event-organizers.')
        ->group(function () {
            Route::middleware(['role:super-admin'])->group(function () {
                Route::get('/', [EventOrganizerController::class, 'index'])->name('index');
                Route::get('/{organizer}', [EventOrganizerController::class, 'show'])->name('show');
            });
            Route::middleware(['role:eo-owner'])->group(function () {
                Route::post('/create', [EventOrganizerController::class, 'store'])->name('store');
                Route::put('/edit/{organizer}', [EventOrganizerController::class, 'update'])->name('update');
            });
            // Route::middleware(['permission:create-event-organizer'])->post('/create', [EventOrganizerController::class, 'store'])->name('store');
            // Route::middleware(['permission:update-event-organizer'])->put('/edit/{id}', [EventOrganizerController::class, 'update'])->name('update');
            // Route::middleware(['permission:delete-event-organizer'])->delete('/{id}', [EventOrganizerController::class, 'destroy'])->name('destroy');
            // Route::middleware(['permission:view-any-event-organizers'])->get('/', [EventOrganizerController::class, 'index'])->name('index');
            // Route::middleware(['permission:view-event-organizer'])->get('/{id}', [EventOrganizerController::class, 'show'])->name('show');
        });

    Route::prefix('documents')
        ->name('documents.')
        ->group(function () {
            Route::middleware(['role:super-admin'])->group(function () {
                Route::get('/', [DocumentController::class, 'index'])->name('index');
                Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
                Route::patch('/{document}/status', [DocumentController::class, 'updateStatus'])->name('updateStatus');
            });
            Route::middleware(['role:eo-owner'])->group(function () {
                Route::post('/create', [DocumentController::class, 'store'])->name('store');
            });
            // Route::middleware(['permission:create-document'])->post('/create', [DocumentController::class, 'store'])->name('store');
            // Route::middleware(['permission:view-any-documents'])->get('/', [DocumentController::class, 'index'])->name('index');
            // Route::middleware(['permission:view-document'])->get('/{document}', [DocumentController::class, 'show'])->name('show');
            // Route::middleware(['permission:update-document-status'])->patch('/{document}/status', [DocumentController::class, 'updateStatus'])->name('updateStatus');
        });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead'])->name('markAsRead');
    });

    Route::prefix('staff')
        ->name('staff.')
        ->middleware(['role:eo-owner'])
        ->group(function () {
            Route::get('/', [StaffController::class, 'index'])->name('index');
            Route::post('/create', [StaffController::class, 'store'])->name('store');
            Route::put('/{staff}', [StaffController::class, 'update'])->name('update');
            Route::delete('/{staff}', [StaffController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('tnc-events')
        ->name('tnc-event.')
        ->middleware(['role:eo-owner'])
        ->group(function () {
            // Route::middleware(['permission:view-tnc-event'])->get('/', [EventTncController::class, 'show'])->name('show');
            // Route::middleware(['permission:accept-tnc-event'])->post('/accept', [EventTncController::class, 'agree'])->name('accept');
            Route::get('/', [EventTncController::class, 'show'])->name('show');
            Route::post('/accept', [EventTncController::class, 'agree'])->name('accept');
        });

    Route::prefix('my/events')
        ->name('my-events.')
        ->middleware(['role:eo-owner'])
        ->group(function () {
            // Route::middleware(['permission:create-event'])->post('/create', [EventController::class, 'store'])->name('create');
            // Route::middleware(['permission:update-event'])->put('/update/{id}', [EventController::class, 'update'])->name('update');
            // Route::middleware(['permission:delete-event'])->delete('/{id}', [EventController::class, 'destroy'])->name('destroy');
            // Route::middleware(['permission:publish-event'])->post('/{id}/publish', [EventController::class, 'publish']);
            Route::get('/', [EventController::class, 'index']);
            Route::get('/{event}', [EventController::class, 'show']);
            Route::post('/create', [EventController::class, 'store'])->name('create');
            Route::put('/update/{event}', [EventController::class, 'update'])->name('update');
            Route::delete('/{event}', [EventController::class, 'destroy'])->name('destroy');
            Route::post('/{event}/publish', [EventController::class, 'publish']);
            Route::post('/{event}/public', [EventController::class, 'publicStatus']);
        });

    Route::prefix('facilities')
        ->name('facility.')
        ->middleware(['role:super-admin|eo-owner'])
        ->group(function () {
            Route::get('/', [FacilityController::class, 'index'])->name('index');
            Route::post('/create', [FacilityController::class, 'store'])->name('store');
            Route::put('/edit/{id}', [FacilityController::class, 'update'])->name('update');
            Route::delete('/{id}', [FacilityController::class, 'destroy'])->name('destroy');
        });

    Route::prefix('tnc')
        ->name('tnc.')
        ->middleware(['role:super-admin'])
        ->group(function () {
            // Route::middleware(['permission:view-any-tnc'])->get('/', [TermAndConController::class, 'index'])->name('index');
            // Route::middleware(['permission:view-latest-tnc'])->get('/{type}/latest', [TermAndConController::class, 'latestByType'])->name('latest');
            // Route::middleware(['permission:create-tnc'])->post('/', [TermAndConController::class, 'store'])->name('store');
            // Route::middleware(['permission:update-tnc'])->put('/{id}', [TermAndConController::class, 'update'])->name('update');
            // Route::middleware(['permission:delete-tnc'])->delete('/{id}', [TermAndConController::class, 'destroy'])->name('destroy');
            Route::get('/', [TermAndConController::class, 'index'])->name('index');
            Route::get('/{type}/latest', [TermAndConController::class, 'latestByType'])->name('latest');
            Route::post('/', [TermAndConController::class, 'store'])->name('store');
            Route::put('/{id}', [TermAndConController::class, 'update'])->name('update');
            Route::delete('/{id}', [TermAndConController::class, 'destroy'])->name('destroy');
        });

    //commit carousel api to git
    Route::prefix('carousels')
        ->name('carousels.')
        ->group(function () {
            Route::get('/all-carousel-list', [CarouselController::class, 'getCarouselList'])->name('get-carousel-list');
            Route::post('/', [CarouselController::class, 'store'])->name('store');
            Route::get('/{id}', [CarouselController::class, 'show'])->name('show');
            Route::put('/{id}', [CarouselController::class, 'update'])->name('update');
            Route::delete('/{id}', [CarouselController::class, 'destroy'])->name('destroy');
        });

    //create endpoint for activity log
    Route::middleware(['can:view-activity-logs'])->get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');
});


// Route::get('/me', function () {
//     $user = auth()->user();
//     return [
//         'user' => $user->email,
//         'roles' => $user->getRoleNames(),
//         'permissions' => $user->getAllPermissions()->pluck('name'),
//         'can_publish' => $user->can('view-any-carousels'),
//     ];
// })->middleware('auth:sanctum');

Route::get('/reset-database', function () {
    if (config('app.env') !== 'local' || config('app.debug') !== true) {
        return response()->json(
            [
                'message' => 'This dangerous endpoint is only available in the local development environment with debug mode enabled.',
                'status' => false,
            ],
            403
        );
    }

    try {
        Artisan::call('migrate:fresh', ['--seed' => true]);
        Artisan::call('cache:clear');
        Artisan::call('config:clear');

        return response()->json([
            'message' => 'SUCCESS: Database has been reset and seeded.',
            'status' => true,
        ]);

    } catch (\Exception $e) {
        Log::error('Failed to reset database via endpoint: ' . $e->getMessage());
        return response()->json([
            'message' => 'An error occurred while resetting the database.',
            'error' => $e->getMessage(),
            'status' => false,
        ], 500); // 500 Internal Server Error
    }
});
