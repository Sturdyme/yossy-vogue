<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\OrderPaymentService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function initializeTopUp(Request $request, PaystackService $paystack)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100|max:1000000',
        ]);

        $user = $request->user();
        $amount = round((float) $validated['amount'], 2);
        $koboAmount = (int) round($amount * 100);
        $reference = 'WLT_' . Str::random(16);

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'currency' => 'NGN']
        );

        $currentBalance = (float) $wallet->balance;

        WalletTransaction::create([
            'wallet_id' => $wallet->id,
            'reference' => $reference,
            'type' => 'credit',
            'amount' => $amount,
            'balance_before' => $currentBalance,
            'balance_after' => $currentBalance,
            'status' => 'pending',
            'description' => 'Wallet Top-Up via Paystack',
        ]);

        try {
            $paymentData = $paystack->initialize([
                'email' => $user->email,
                'amount' => $koboAmount,
                'reference' => $reference,
                'callback_url' => route('wallet.callback'),
                'metadata' => [
                    'payment_type' => 'wallet_topup',
                    'user_id' => $user->id,
                    'wallet_id' => $wallet->id,
                ],
            ]);

            return response()->json([
                'authorization_url' => $paymentData['authorization_url'],
                'reference' => $reference,
            ]);
        } catch (\Throwable $e) {
            logger()->error('Wallet funding initialization failed: ' . $e->getMessage());

            return response()->json([
                'message' => 'Unable to initialize funding.',
            ], 500);
        }
    }

    public function handleWebhook(Request $request, OrderPaymentService $orderPaymentService)
    {
        $paystackSignature = $request->header('x-paystack-signature');
        $secretKey = config('services.paystack.secret_key');

        $expected = hash_hmac('sha512', $request->getContent(), $secretKey);

        if (!$paystackSignature || !hash_equals($expected, $paystackSignature)) {
            return response()->json(['message' => 'Invalid Signature'], 400);
        }

        $payload = $request->all();

        if (($payload['event'] ?? '') === 'charge.success') {
            $data = $payload['data'];
            $reference = $data['reference'];
            $paidAmount = $data['amount'] / 100;
            $metadata = $data['metadata'] ?? [];

            if (($metadata['payment_type'] ?? '') === 'wallet_topup') {
                $this->creditWalletSafely($reference, $paidAmount, $metadata);
            } elseif (isset($metadata['order_id'])) {
                $orderPaymentService->complete($reference, $paidAmount);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function creditWalletSafely(string $reference, float $paidAmount, array $metadata)
    {
        DB::transaction(function () use ($reference, $paidAmount) {
            $transaction = WalletTransaction::where('reference', $reference)
                ->lockForUpdate()
                ->first();

            if (!$transaction || $transaction->status === 'success') {
                return;
            }

            if (abs((float) $transaction->amount - (float) $paidAmount) > 0.01) {
                $transaction->update([
                    'status' => 'failed',
                    'description' => 'Flagged: Paid amount does not match transaction record amount.',
                ]);
                logger()->critical("Fraud attempt on reference: {$reference}. Expected {$transaction->amount}, got {$paidAmount}");
                return;
            }

            $wallet = Wallet::where('id', $transaction->wallet_id)->lockForUpdate()->first();

            if (!$wallet) {
                return;
            }

            $balanceBefore = round((float) $wallet->balance, 2);
            $balanceAfter = round($balanceBefore + $paidAmount, 2);

            $wallet->update(['balance' => $balanceAfter]);

            $transaction->update([
                'status' => 'success',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);
        });
    }

    public function verifyTopUp(string $reference, Request $request, PaystackService $paystack)
    {
        // Only the owner of the wallet can look up or verify a top-up reference.
        $wallet = Wallet::where('user_id', $request->user()->id)->first();

        $transaction = $wallet
            ? WalletTransaction::where('reference', $reference)->where('wallet_id', $wallet->id)->first()
            : null;

        if (!$transaction) {
            return response()->json(['message' => 'Transaction reference not found'], 404);
        }

        // If the webhook already processed it, return success immediately
        if ($transaction->status === 'success') {
            return response()->json([
                'status' => 'success',
                'message' => 'Wallet funded successfully',
                'transaction' => $transaction,
            ]);
        }

        try {
            $paystackData = $paystack->verify($reference);

            if (($paystackData['status'] ?? false) && ($paystackData['data']['status'] ?? '') === 'success') {
                $paidAmount = $paystackData['data']['amount'] / 100;
                $metadata = $paystackData['data']['metadata'] ?? [];

                $this->creditWalletSafely($reference, $paidAmount, $metadata);

                $updatedTransaction = WalletTransaction::where('reference', $reference)->first();

                if ($updatedTransaction->status !== 'success') {
                    return response()->json([
                        'message' => 'Payment could not be applied to your wallet. Please contact support.',
                    ], 422);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Payment verified and wallet funded!',
                    'transaction' => $updatedTransaction,
                ]);
            }

            return response()->json(['message' => 'Payment status is not successful'], 400);
        } catch (\Throwable $e) {
            logger()->error('Verification failed: ' . $e->getMessage());

            return response()->json(['message' => 'Unable to verify payment with Paystack'], 500);
        }
    }

    public function payWithWallet(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required_without:items.*.id|exists:products,id',
            'items.*.id' => 'required_without:items.*.product_id|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1|max:100',
            'fulfillment_method' => 'sometimes|in:delivery,pickup',
        ]);

        $user = $request->user();
        $fulfillmentMethod = $validated['fulfillment_method'] ?? 'delivery';

        // Merge repeated products so stock is checked against the combined quantity.
        $wanted = collect($validated['items'])
            ->groupBy(fn ($item) => $item['product_id'] ?? $item['id'])
            ->map(fn ($group) => (int) $group->sum('quantity'));

        $required = null;

        try {
            $order = DB::transaction(function () use ($user, $wanted, $fulfillmentMethod, &$required) {
                // Lock the product rows so two simultaneous orders cannot oversell the same stock.
                $products = Product::whereIn('id', $wanted->keys())
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                $items = $wanted->map(function ($quantity, $productId) use ($products) {
                    $product = $products->get($productId);

                    if (!$product) {
                        throw new \DomainException('A product in your cart is no longer available.');
                    }

                    if ((int) $product->stock < $quantity) {
                        throw new \DomainException("Only {$product->stock} left of {$product->name}.");
                    }

                    return [
                        'product_id' => $product->id,
                        'product_name' => $product->name,
                        'quantity' => $quantity,
                        'price' => (float) $product->price,
                    ];
                })->values();

                $subtotal = round($items->sum(fn ($i) => $i['price'] * $i['quantity']), 2);
                $shipping = $fulfillmentMethod === 'delivery' ? round($subtotal * 0.15, 2) : 0;
                $totalAmount = round($subtotal + $shipping, 2);

                $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

                if (!$wallet || round((float) $wallet->balance, 2) < $totalAmount) {
                    $required = $totalAmount;
                    throw new \DomainException('Insufficient wallet balance.');
                }

                $reference = 'WLTPAY_' . Str::random(12);
                $balanceBefore = round((float) $wallet->balance, 2);
                $balanceAfter = round($balanceBefore - $totalAmount, 2);

                $wallet->update(['balance' => $balanceAfter]);

                WalletTransaction::create([
                    'wallet_id' => $wallet->id,
                    'reference' => $reference,
                    'type' => 'debit',
                    'amount' => $totalAmount,
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'status' => 'success',
                    'description' => 'Order payment via wallet balance',
                ]);

                $order = Order::create([
                    'user_id' => $user->id,
                    'reference' => $reference,
                    'subtotal' => $subtotal,
                    'shipping' => $shipping,
                    'total_amount' => $totalAmount,
                    'status' => 'completed',
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

                    Product::where('id', $item['product_id'])->decrement('stock', $item['quantity']);
                }

                return $order;
            });
        } catch (\DomainException $e) {
            $payload = ['message' => $e->getMessage()];

            if ($required !== null) {
                $payload['required'] = $required;
            }

            return response()->json($payload, 422);
        }

        // Load the items relation under whichever name your Order model uses,
        // so a naming mismatch can never fail the request after the wallet was charged.
        foreach (['items', 'item'] as $relation) {
            if (method_exists($order, $relation)) {
                $order->load($relation);
                break;
            }
        }

        return response()->json([
            'message' => 'Order placed successfully using wallet balance.',
            'order' => $order,
        ], 201);
    }

    public function getWallet(Request $request)
    {
        $user = $request->user();

        $wallet = Wallet::firstOrCreate(
            ['user_id' => $user->id],
            ['balance' => 0.00, 'currency' => 'NGN']
        );

        $transactions = WalletTransaction::where('wallet_id', $wallet->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'wallet' => $wallet,
            'transactions' => $transactions,
        ]);
    }

    public function callback(Request $request, PaystackService $paystack)
    {
        $reference = $request->query('reference');
        $frontendUrl = config('app.frontend_url');

        if (!$reference) {
            return redirect("{$frontendUrl}/wallet/callback?status=failed&message=no_reference");
        }

        $paystackData = $paystack->verify($reference);

        if (
            ($paystackData['status'] ?? false) === true &&
            ($paystackData['data']['status'] ?? '') === 'success'
        ) {
            $paidAmount = $paystackData['data']['amount'] / 100;
            $metadata = $paystackData['data']['metadata'] ?? [];

            // This callback only reports on wallet top-ups.
            if (($metadata['payment_type'] ?? '') === 'wallet_topup') {
                $this->creditWalletSafely($reference, $paidAmount, $metadata);

                $transaction = WalletTransaction::where('reference', $reference)->first();

                if ($transaction && $transaction->status === 'success') {
                    return redirect("{$frontendUrl}/wallet/callback?reference={$reference}&status=success");
                }
            }
        }

        return redirect("{$frontendUrl}/wallet/callback?reference={$reference}&status=failed");
    }
}
