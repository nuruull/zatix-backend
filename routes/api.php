<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\Auth\AuthController;
use App\Http\Controllers\API\Admin\UserController;
use App\Http\Controllers\API\TermAndConController;
use App\Http\Controllers\API\Events\EventController;
use App\Http\Controllers\API\Events\StaffController;
use App\Http\Controllers\API\Events\RundownController;
use App\Http\Controllers\API\Events\EventTncController;
use App\Http\Controllers\API\Log\ActivityLogController;
use App\Http\Controllers\API\Auth\NewPasswordController;
use App\Http\Controllers\API\General\CarouselController;
use App\Http\Controllers\API\Tickets\MyTicketController;
use App\Http\Controllers\API\Tickets\TicketQRController;
use App\Http\Controllers\API\Documents\DocumentController;
use App\Http\Controllers\API\Events\EventPublicController;
use App\Http\Controllers\API\Transactions\OrderController;
use App\Http\Controllers\API\Facilities\FacilityController;
use App\Http\Controllers\API\Cashier\OfflineSalesController;
use App\Http\Controllers\API\General\NotificationController;
use App\Http\Controllers\API\Events\EventOrganizerController;
use App\Http\Controllers\API\Auth\PasswordResetLinkController;
use App\Http\Controllers\API\Tickets\TicketValidationController;
use App\Http\Controllers\API\Transactions\MidtransWebhookController;
use App\Http\Controllers\API\Transactions\FinancialTransactionController;


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

Route::post('/webhooks/midtrans', [MidtransWebhookController::class, 'handle'])->name('webhooks.midtrans');

//membuat sebuah endpoint di aplikasi Laravel Anda yang meniru respons sukses dari Midtrans secepat mungkin.
Route::post('/mock/charge', function () {
    // Endpoint ini meniru respons sukses dari Midtrans secepat mungkin.
    // Tidak ada logika, hanya mengembalikan JSON.
    return response()->json([
        'transaction_id' => 'mock-tx-' . uniqid(),
        'order_id' => request('transaction_details.order_id', 'mock-order-' . uniqid()),
        'status_code' => '200',
        'transaction_status' => 'pending',
        'payment_type' => 'bank_transfer',
        'gross_amount' => request('transaction_details.gross_amount', '100000.00'),
        'va_numbers' => [
            ['bank' => 'bca', 'va_number' => '1234567890']
        ],
        'expiry_time' => now()->addDay()->toDateTimeString(),
    ]);
});

Route::get('/debug-permissions', function () {
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
            // Super Admin
            Route::get('/', [EventOrganizerController::class, 'index'])->name('index');
            Route::get('/{organizer}', [EventOrganizerController::class, 'show'])->name('show');

            // EO Owner
            Route::post('/create', [EventOrganizerController::class, 'store'])->name('store');
            Route::get('/me/profile', [EventOrganizerController::class, 'showMyProfile'])->name('show.my-profile');
            // Gunakan POST untuk update karena form-data tidak mendukung PUT dengan file
            Route::put('/{organizer}', [EventOrganizerController::class, 'update'])->name('update');
        });

    Route::prefix('documents')
        ->name('documents.')
        ->group(function () {
            Route::middleware(['role:super-admin'])->group(function () {
                Route::get('/', [DocumentController::class, 'index'])->name('index');
                Route::get('/{document}', [DocumentController::class, 'show'])->name('show');
                Route::put('/{document}/status', [DocumentController::class, 'updateStatus'])->name('updateStatus');
            });
            Route::middleware(['role:eo-owner'])->group(function () {
                Route::post('/create', [DocumentController::class, 'store'])->name('store');
            });
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
            Route::get('/', [EventTncController::class, 'show'])->name('show');
            Route::post('/accept', [EventTncController::class, 'agree'])->name('accept');
        });

    Route::prefix('my/events')
        ->name('my-events.')
        ->middleware(['role:eo-owner'])
        ->group(function () {
            Route::get('/', [EventController::class, 'index'])->name('index');
            Route::get('/{event}', [EventController::class, 'show'])->name('show');
            Route::post('/create', [EventController::class, 'store'])->name('store');
            Route::put('/update/{event}', [EventController::class, 'update'])->name('update');
            Route::delete('/{event}', [EventController::class, 'destroy'])->name('destroy');
            Route::post('/{event}/publish', [EventController::class, 'publish'])->name('publish');
            Route::post('/{event}/public', [EventController::class, 'publicStatus'])->name('public');
        });

    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['role:super-admin']) // <-- KUNCI KEAMANAN UTAMA
        ->group(function () {
            Route::get('/events', [\App\Http\Controllers\API\Admin\EventController::class, 'index'])->name('events.index');
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/roles', [UserController::class, 'getRoles'])->name('roles.index');
            Route::put('/users/{user}/roles', [UserController::class, 'updateRoles'])->name('users.update-roles');

        });

    Route::prefix('events')->name('events.')->group(function () {
        Route::get('/{event}/rundowns', [RundownController::class, 'index'])->name('events.rundowns.index');
        Route::post('/{event}/rundowns', [RundownController::class, 'store'])->name('events.rundowns.store');
    });
    Route::prefix('rundowns')->name('rundowns.')->group(function () {
        Route::get('/{rundown}', [RundownController::class, 'show'])->name('rundowns.show');
        Route::put('/{rundown}', [RundownController::class, 'update'])->name('rundowns.update');
        Route::delete('/{rundown}', [RundownController::class, 'destroy'])->name('rundowns.destroy');
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
            Route::get('/', [TermAndConController::class, 'index'])->name('index');
            Route::get('/{type}/latest', [TermAndConController::class, 'latestByType'])->name('latest');
            Route::post('/', [TermAndConController::class, 'store'])->name('store');
            Route::put('/{id}', [TermAndConController::class, 'update'])->name('update');
            Route::delete('/{id}', [TermAndConController::class, 'destroy'])->name('destroy');
        });

    //commit carousel api to git
    Route::prefix('carousels')
        ->name('carousels.')
        ->middleware(['role:super-admin'])
        ->group(function () {
            Route::get('/all-carousel-list', [CarouselController::class, 'getCarouselList'])->name('get-carousel-list');
            Route::post('/create', [CarouselController::class, 'store'])->name('store');
            Route::get('/{id}', [CarouselController::class, 'show'])->name('show');
            Route::put('/{id}', [CarouselController::class, 'update'])->name('update');
            Route::delete('/{id}/destroy', [CarouselController::class, 'destroy'])->name('destroy');
        });

    //create endpoint for activity log
    Route::middleware(['can:view-activity-logs'])->get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs');

    Route::prefix('orders')
        ->name('orders.')
        ->group(function () {
            Route::post('/', [OrderController::class, 'store'])->name('store');
        });

    Route::prefix('e-tickets')
        ->name('e-tickets.')
        ->group(function () {
            Route::get('/{ticket_code}/qr', [TicketQRController::class, 'show'])->name('show-qr');
            Route::middleware(['role:crew|eo-owner'])->group(function () {
                Route::get('/', [TicketValidationController::class, 'index'])->name('index');
            });
            Route::middleware(['role:crew'])->group(function () {
                Route::post('/validate', [TicketValidationController::class, 'validateTicket'])->name('validate-ticket');
            });
        });

    Route::prefix('my-tickets')
        ->name('my-tickets.')
        ->group(function () {
            Route::get('/', [MyTicketController::class, 'index'])->name('index');
            Route::get('/{eTicket:ticket_code}', [MyTicketController::class, 'show'])->name('show');
        });

    Route::prefix('events')->name('events.')->group(function () {
        Route::get('/{event}/financial-transactions', [FinancialTransactionController::class, 'index'])->name('financial-transactions.index');
        Route::post('/{event}/financial-transactions', [FinancialTransactionController::class, 'store'])->name('financial-transactions.store');
    });
    Route::prefix('financial-transactions')->name('financial-transactions.')->group(function () {
        Route::get('/{financial_transaction}', [FinancialTransactionController::class, 'show'])->name('show');
        Route::put('/{financial_transaction}', [FinancialTransactionController::class, 'update'])->name('update');
        Route::delete('/{financial_transaction}', [FinancialTransactionController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('cashier')
    ->name('cashier.')
    ->middleware(['role:cashier'])
    ->group(function () {
        Route::post('/sales', [OfflineSalesController::class, 'store'])->name('sales.store');

    });
});

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
