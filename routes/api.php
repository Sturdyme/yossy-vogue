<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;



Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    
  Route::post('/pay', [PaymentController::class, 'initialize'])->name('payment.initialize'); 
});

Route::get('/verify/{reference}', [PaymentController::class, 'verify']);

Route::get('/payment/callback', [PaymentController::class, 'callback'])->name('payment.callback');

Route::post('/paystack/webhook', [WebhookController::class, 'handle']);
