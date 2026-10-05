<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Services\OrderPaymentService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function initialize(Request $request, PaystackService $paystack)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:1',
            'fulfillment_method' => 'sometimes|in:delivery,pickup'
        ]);

        $items = collect($request->items)->map(function ($item) {
          $product = Product::findOrFail($item['product_id'] ?? $item['id']);

          return [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => (int) $item['quantity'],
            'price' => $product->price,

          ];
        });

        $fulfillmentMethod = $validated['fulfillment_method'] ?? 'delivery';

        $subtotal =  $items->sum(fn ($i) => $i['price'] * $i['quantity']);

        foreach ($items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        $shipping = $fulfillmentMethod === 'delivery' ? $subtotal * 0.15 : 0;
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
            'user_id' => $user->id,
            'reference' => $reference,
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total_amount' => $totalAmount,
            'status' => 'pending',
            'fulfillment_method' => $fulfillmentMethod,
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
        try {
            $paymentData = $paystack->initialize([
                'email' => $user->email,
                'amount' => $koboAmount,
                'reference' => $reference,
                'metadata' => ['order_id' => $order->id]
            ]);
        } catch (\Throwable $e) {
            error_log('PAYMENT CONTROLLER ERROR: ' . $e->getMessage());
            error_log('TRACE: ' . $e->getTraceAsString());
            throw $e;
        }

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

    public function verify($reference, PaystackService $paystack, OrderPaymentService $orderPayments)
{
    $response = $paystack->verify($reference);

    if (
        ($response['status'] ?? false) === true &&
        ($response['data']['status'] ?? '') === 'success' &&
        $orderPayments->complete($reference, $response['data']['amount'] / 100)
    ) {
        return response()->json(['status' => 'success']);
    }

    return response()->json([
        'status'  => 'failed',
        'message' => $response['message'] ?? 'Payment verification failed',
    ], 400);
}

public function callback(Request $request, PaystackService $paystack, OrderPaymentService $orderPayments)
{
    $reference   = $request->query('reference');
    $frontendUrl = config('app.frontend_url');

    if (!$reference) {
        return redirect("{$frontendUrl}/payment-success?status=failed&message=no_reference");
    }

    $response = $paystack->verify($reference);

    if (
        ($response['status'] ?? false) === true &&
        ($response['data']['status'] ?? '') === 'success' &&
        $orderPayments->complete($reference, $response['data']['amount'] / 100)
    ) {
        return redirect("{$frontendUrl}/payment-success?reference={$reference}");
    }

    return redirect("{$frontendUrl}/payment-success?status=failed");
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
