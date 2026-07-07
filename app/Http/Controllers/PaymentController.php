<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function initialize(Request $request, PaystackService $paystack)
    {
        $request->validate([
            'items' => 'required|array|min:1',
        ]);

        $subtotal = 0;

        foreach ($request->items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $shipping = $subtotal * 0.15;
        $totalAmount = $subtotal + $shipping;

           $koboAmount = (int) round($totalAmount * 100);

        $reference = 'YUNA_' . Str::random(12);

        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

     

        // ✅ CREATE ORDER
        $order = Order::create([
            'user_id' => $user->id,   // FIXED
            'reference' => $reference,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total_amount' => $totalAmount,
            'status' => 'pending',
        ]);

         foreach ($request->items as $item) {

         OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $item['product_id'] ?? $item['id'],
            'product_name' => $item['name'] ?? $item['title'] ?? 'Unknown Product',
            'quantity' => $item['quantity'],
            'price' => $item['price'],
         ]);
         }

        

        // ✅ INIT PAYSTACK (EMAIL REQUIRED)
        $paymentData = $paystack->initialize([
            'email' => $user->email,  // FIXED (Paystack requirement)
            'amount' => $koboAmount,
            'reference' => $reference,
            'metadata' => [
                'order_id' => $order->id,
            ]
        ]);

        // ✅ STORE PAYMENT
        Payment::create([
            'user_id' => $user->id,
            'order_id' => $order->id ?? null,
            'reference' => $reference,
            'amount' => $totalAmount,
            'status' => 'pending',
        ]);

        return response()->json([
            'authorization_url' => $paymentData['authorization_url']
        ]);
    }

    public function verify($reference, PaystackService $paystack)
    {
        $response = $paystack->verify($reference);

        if (
            isset($response['status']) &&
            $response['status'] === true &&
            isset($response['data']) &&
            $response['data']['status'] === 'success'
        ) {
            $this->completeOrder(
                $reference,
                $response['data']['metadata']['order_id'] ?? null
            );

            return response()->json(['status' => 'success']);
        }

        return response()->json([
            'status' => 'failed',
            'message' => $response['message'] ?? 'Payment verification failed'
        ], 400);
    }

    public function callback(Request $request, PaystackService $paystack)
    {
        $reference = $request->query('reference');

        if (!$reference) {
            return response()->json([
                'status' => 'failed',
                'message' => 'No reference supplied'
            ], 400);
        }

        $response = $paystack->verify($reference);

        if (
            isset($response['status']) &&
            $response['status'] === true &&
            isset($response['data']) &&
            $response['data']['status'] === 'success'
        ) {
            $this->completeOrder(
                $reference,
                $response['data']['metadata']['order_id'] ?? null
            );

            return redirect("http://localhost:5173/payment-success?reference={$reference}");
        }

        return redirect("http://localhost:5173/payment-success?status=failed");
    }

    private function completeOrder($reference, $orderId)
    {
        if (!$orderId) return;

        $payment = Payment::where('reference', $reference)->first();

        if ($payment) {
            $payment->update(['status' => 'success']);
        }

        Order::where('id', $orderId)->update([
            'status' => 'completed',
            
        ]);
    }
}