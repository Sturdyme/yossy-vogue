<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderPaymentService
{
    /**
     * Create a new class instance.
     **** Marks a payment and its order as complete.
     * Safe to call repeatedly for the same reference.
     * Returns true if the payment is (now or already) successful.
     */
    public function complete(string $reference, float $paidAmount): bool
    {
        return DB::transaction(function () use ($reference, $paidAmount) {
         $payment = Payment::where('reference', $reference)
         ->lockForUpdate()
         ->first();

         if (!$payment) {
            return false;
         }

         if ($payment->status === 'success') {
            return true;
         }

         if (abs((float) $payment->amount - $paidAmount) > 0.01) {
            Log::warning('Paystack payment amount mismatch', [
                'reference' => $reference,
                'expected_amount' => $payment->amount,
                'paid' => $paidAmount,
            ]);
            return false;
         }

         Order::where('id', $payment->order_id)
         ->where('status', 'pending')
         ->update(['status' => 'completed']);

         return true;
        });
    }
}
