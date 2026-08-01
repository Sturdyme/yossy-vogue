<?php

use App\Http\Controllers\AIController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\OtpController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/otp/send', [OtpController::class, 'sendOtp']);
Route::post('/otp/verify', [OtpController::class, 'verifyOtp']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::post('/pay', [PaymentController::class, 'initialize'])->name('payment.initialize'); 
});

Route::get('/verify/{reference}', [PaymentController::class, 'verify']);

Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/orders', [OrderController::class, 'userOrders']);
    Route::get('/orders/{order_id}', [OrderController::class, 'show']);
});

Route::get('/otp/resend', [OtpController::class, 'sendOtp']);
Route::get('/otp/verify/{otp}', [OtpController::class, 'verifyOtp']);

Route::post('/paystack/webhook', [WebhookController::class, 'handle']);

Route::post('/chat', [AIController::class, 'chat']);

Route::get('/debug-payment', function () {
    try {
        $user = \App\Models\User::first();
        $order = \App\Models\Order::create([
            'user_id' => $user->id,
            'reference' => 'DEBUG_' . \Illuminate\Support\Str::random(12),
            'subtotal' => 1000,
            'shipping' => 150,
            'total_amount' => 1150,
            'status' => 'pending',
        ]);
        return response()->json(['success' => true, 'order_id' => $order->id]);
    } catch (\Throwable $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ], 500);
    }
});