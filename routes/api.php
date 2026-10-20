
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AIController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WalletController;

/*
|--------------------------------------------------------------------------
| Public Authentication Routes
|--------------------------------------------------------------------------
*/

Route::post('/register', [AuthController::class, 'register']);

Route::post('/login', [AuthController::class, 'login']);

Route::post('/otp/resend', [OtpController::class, 'sendOtp'])
    ->middleware('throttle:5,1');

Route::post('/otp/verify', [OtpController::class, 'verifyOtp'])
    ->middleware('throttle:10,1');


/*
|--------------------------------------------------------------------------
| Public Product Routes
|--------------------------------------------------------------------------
*/

Route::get('/products', [ProductController::class, 'index']);

Route::get('/products/search', [ProductController::class, 'search']);

Route::get('/products/featured', [ProductController::class, 'featured']);

Route::get('/products/deals', [ProductController::class, 'deals']);

// Keep the dynamic product route after specific product routes.
Route::get('/products/{product}', [ProductController::class, 'show']);


/*
|--------------------------------------------------------------------------
| Authenticated Routes
|--------------------------------------------------------------------------
|
| These routes require authentication but do not inherently require
| email verification.
|
*/

Route::middleware('auth:sanctum')->group(function () {

    // Authenticated user
    Route::get('/user', function (Request $request) {
        $user = $request->user();

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_verified' => (bool) $user->is_verified,
            ],
        ]);
    });

    // Session management
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/logout-all', [AuthController::class, 'logoutAll']);

    // Account management
    Route::put('/user/profile', [UserController::class, 'updateProfile']);

    Route::put('/user/password', [UserController::class, 'updatePassword']);

    Route::put('/user/address', [UserController::class, 'updateAddress']);

    Route::put('/user/notifications', [UserController::class, 'updateNotifications']);

    Route::put('/user/privacy', [UserController::class, 'updatePrivacy']);

    Route::post('/user/data-export', [UserController::class, 'exportData']);

    Route::delete('/user', [UserController::class, 'destroy']);


    /*
    |--------------------------------------------------------------------------
    | Verified User Routes
    |--------------------------------------------------------------------------
    |
    | These routes require both authentication and email verification.
    |
    */

    Route::middleware('verified')->group(function () {

        // Orders
        Route::get('/orders', [OrderController::class, 'userOrders']);

        Route::get('/orders/{order_id}', [OrderController::class, 'show']);

        // Payment initialization
        Route::post('/pay', [PaymentController::class, 'initialize'])
            ->name('payment.initialize');

        // Wallet
        Route::get('/wallet', [WalletController::class, 'getWallet']);

        Route::post('/wallet/top-up', [WalletController::class, 'initializeTopUp']);

        Route::get('/wallet/verify/{reference}', [WalletController::class, 'verifyTopUp']);

        Route::post('/wallet/pay', [WalletController::class, 'payWithWallet']);
    });


    /*
    |--------------------------------------------------------------------------
    | Admin Routes
    |--------------------------------------------------------------------------
    |
    | Authentication + email verification + admin authorization.
    | Remove 'verified' here only if you intentionally allow unverified
    | administrators to use these endpoints.
    |
    */

    Route::middleware(['verified', 'admin'])
        ->prefix('admin')
        ->group(function () {

            // Product management
            Route::post('/products', [ProductController::class, 'store']);

            Route::put('/products/{product}', [ProductController::class, 'update']);

            Route::delete('/products/{product}', [ProductController::class, 'destroy']);

            // Order management
            Route::get('/orders', [OrderController::class, 'allOrders']);

            Route::put('/orders/{order}/status', [OrderController::class, 'updateStatus']);

            // User management
            Route::get('/users', [UserController::class, 'index']);

            Route::put('/users/{user}/role', [UserController::class, 'updateRole']);

            // Admin dashboard
            Route::get('/dashboard', [AdminController::class, 'dashboardStats']);

            // Admin PIN management
            Route::post('/pin/set', [UserController::class, 'setAdminPin']);

            Route::post('/pin/verify', [UserController::class, 'verifyAdminPin']);
        });
});


/*
|--------------------------------------------------------------------------
| Public Payment Callbacks and Webhooks
|--------------------------------------------------------------------------
|
| These endpoints must remain accessible to their external callers.
| Verify payment signatures/references inside the relevant controllers.
|
*/

Route::post('/paystack/webhook', [WalletController::class, 'handleWebhook']);

Route::get('/verify/{reference}', [PaymentController::class, 'verify']);

Route::get('/payment/callback', [PaymentController::class, 'callback'])
    ->name('payment.callback');

Route::get('/wallet/callback', [WalletController::class, 'callback'])
    ->name('wallet.callback');


/*
|--------------------------------------------------------------------------
| AI Support
|--------------------------------------------------------------------------
*/

Route::post('/chat', [AIController::class, 'chat'])
    ->middleware('throttle:20,1');
