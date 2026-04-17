<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payment;
use App\Services\PaystackService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function initialize(Request $request, PaystackService $paystack)
{
    // 1. Validate the incoming request
    $request->validate([
        'amount' => 'required|numeric|min:1',
    ]);

    // 2. Capture the Naira amount from React
    $nairaAmount = $request->amount;

    // 3. Convert to Kobo (Integer) for Paystack
    // Using round() and (int) ensures no decimals are sent
    $koboAmount = (int) round($nairaAmount * 1000);

    // 4. Create the Order
    $order = Order::create([
        'user_id' => auth()->id(),
        'amount' => $nairaAmount, // Store the human-readable Naira amount
        'status' => 'pending',
    ]);

    // 5. Initialize Paystack

    $paymentData = $paystack->initialize([
        'email' => auth()->user()->email,
        'amount' => $koboAmount, // Pass the converted Kobo amount
        'metadata' => [
            'order_id' => $order->id
        ]
    ]);

    // 6. Create the Payment Record
    Payment::create([
        'user_id' => auth()->id(),
        'order_id' => $order->id,
        'reference' => $paymentData['reference'],
        'amount' => $nairaAmount, // Store Naira amount
        'status' => 'pending',
    ]);
   
    // 7. Handle Response
    if ($request->expectsJson()) {
        return response()->json([
            'authorization_url' => $paymentData['authorization_url']
        ]);
    }

    return redirect($paymentData['authorization_url']);
}

     public function verify($reference, PaystackService $paystack)
     {
        $response = $paystack->verify($reference);

        if ($response['data'] ['status'] === 'success') {
            $payment = Payment::where('reference', $reference)->first();
            $payment->update(['status' => 'success']);

            $orderId = $response['data']['metadata']['order_id'];

            Order::where('id', $orderId)->update([
                'status' => 'paid',
                'payment_reference' => $reference
            ]);

            return response()->json([
                'status' => 'success'
            ]);
        }
        return response()->json([
            'status' => 'failed'
        ]);
     }

    /**
     * Paystack payment callback handler
     * Verifies payment and updates order status
     */
    public function callback(Request $request, PaystackService $paystack)
    {
        $reference = $request->query('reference');
        if (!$reference) {
            return response()->json(['status' => 'failed', 'message' => 'No reference supplied'], 400);
        }

        $response = $paystack->verify($reference);

        if (isset($response['data']['status']) && $response['data']['status'] === 'success') {
            $payment = Payment::where('reference', $reference)->first();
            if ($payment) {
                $payment->update(['status' => 'success']);
                $orderId = $response['data']['metadata']['order_id'] ?? null;
                if ($orderId) {
                    Order::where('id', $orderId)->update([
                        'status' => 'paid',
                        'payment_reference' => $reference
                    ]);
                }
            }
            return response()->json(['status' => 'success']);
        }
        return response()->json(['status' => 'failed', 'message' => 'Payment verification failed']);
    }
}