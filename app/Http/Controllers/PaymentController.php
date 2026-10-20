<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Services\OrderPaymentService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function initialize(Request $request, PaystackService $paystack)
    {
        $user = $request->user();

        $validated = $request->validate([
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required_without:items.*.id|exists:products,id',
            'items.*.id' => 'required_without:items.*.product_id|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'fulfillment_method' => 'sometimes|in:delivery,pickup',
        ]);

        $fulfillmentMethod = $validated['fulfillment_method'] ?? 'delivery';

        // Name, price and stock always come from the database, never from the browser.
        $lines = collect($validated['items'])->map(fn ($item) => [
            'product_id' => $item['product_id'] ?? $item['id'],
            'quantity' => (int) $item['quantity'],
        ]);

        $products = Product::whereIn('id', $lines->pluck('product_id'))->get()->keyBy('id');

        $items = $lines->map(function ($line) use ($products) {
            $product = $products->get($line['product_id']);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $line['quantity'],
                'price' => (float) $product->price,
                'stock' => (int) $product->stock,
            ];
        });

        foreach ($items as $item) {
            if ($item['stock'] < $item['quantity']) {
                return response()->json([
                    'message' => "Only {$item['stock']} left of {$item['product_name']}.",
                ], 422);
            }
        }

        // The subtotal is counted exactly once.
        $subtotal = round($items->sum(fn ($i) => $i['price'] * $i['quantity']), 2);
        $shipping = $fulfillmentMethod === 'delivery' ? round($subtotal * 0.15, 2) : 0;
        $totalAmount = round($subtotal + $shipping, 2);
        $koboAmount = (int) round($totalAmount * 100);

        $reference = 'YUNA_' . Str::random(12);

        try {
            // If Paystack fails, the whole transaction rolls back: no orphan order is left behind.
            $paymentData = DB::transaction(function () use (
                $user, $items, $reference, $subtotal, $shipping, $totalAmount, $fulfillmentMethod, $koboAmount, $paystack
            ) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'subtotal' => $subtotal,
                    'shipping' => $shipping,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'fulfillment_method' => $fulfillmentMethod,
                ]);

                foreach ($items as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'product_id' => $item['product_id'],
                        'product_name' => $item['product_name'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price'],
                    ]);
                }

                Payment::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'reference' => $reference,
                    'amount' => $totalAmount,
                    'status' => 'pending',
                ]);

                return $paystack->initialize([
                    'email' => $user->email,
                    'amount' => $koboAmount,
                    'reference' => $reference,
                    'callback_url' => route('payment.callback'),
                    'metadata' => ['order_id' => $order->id],
                ]);
            });
        } catch (\Throwable $e) {
            logger()->error('Payment initialization failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Unable to start payment. Please try again.',
            ], 500);
        }

        return response()->json([
            'authorization_url' => $paymentData['authorization_url'],
            'reference' => $reference,
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
            'status' => 'failed',
            'message' => $response['message'] ?? 'Payment verification failed',
        ], 400);
    }

    public function callback(Request $request, PaystackService $paystack, OrderPaymentService $orderPayments)
    {
        $reference = $request->query('reference');
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
}
