<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WalletController extends Controller
{
    public function initializeTopUp(Request $request, PaystackService $paystack)
    {

        $validated = $request->validate([
            'amount' => 'required|numeric|min:100'
        ]);

        $user = auth()->user();
        if(!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }
        $amount = $validated['amount'];
        $koboAmount = (int) round($amount * 100);
        $reference = 'WLT_' . Str::random(16);

      $wallet = Wallet::firstOrCreate([
        'user_id' => $user->id],
        ['balance' => 0.00, 'currency' => 'NGN']
        );

        $currentBalance = floatval($wallet->balance);

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
            'metadata' =>[
                'payment_type' => 'wallet_topup',
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
            ]
            ]);

            return response()->json([
                'authorization_url' => $paymentData['authorization_url'],
                'reference' => $reference,
            ]);
        } catch (\Throwable $e) {
            logger()->error('Wallet funding initialization failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Unable to initialize funding.'
            ], 500);
        }
    }

    public function handleWebhook(Request $request)
    {
        $paystackSignature = $request->header('x-paystack-signature');
        $secretKey = config('services.paystack.secret_key');

        if (!$paystackSignature || $paystackSignature !== hash_hmac('sha512', $request->getContent(), $secretKey)) {
            return response()->json([
                'message' => 'Invalid Signature'
            ], 400);
        }
        $payload = $request->all();

        if (($payload['event'] ?? '') === 'charge.success') {
            $data = $payload['data'];
            $reference = $data['reference'];
            $paidAmount = $data['amount'] / 100;
            $metadata = $data['metadata'] ?? [];

            if (($metadata['payment_type'] ?? '') === 'wallet_topup') {
                $this->creditWalletSafely($reference, $paidAmount, $metadata);
            }
        }
        return response()->json([
            'status' => 'success'
        ], 200);
    }

    private function creditWalletSafely(string $reference, float $paidAmount, array $metadata)
    {
        DB::transaction(function () use ($reference, $paidAmount, $metadata) {
         $transaction = WalletTransaction::where('reference', $reference)->first();

         if (!$transaction || $transaction->status === 'success') {
            return;
         }

         if ((float) $transaction->amount !== (float) $paidAmount) {
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

         $balanceBefore = $wallet->balance;
         $balanceAfter = $balanceBefore + $paidAmount;

         $wallet->update([
            'balance' => $balanceAfter
         ]);

         $transaction->update([
            'status' => 'success',
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
         ]);

        });
    }

    public function verifyTopUp(string $reference, PaystackService $paystack)
{
    $transaction = WalletTransaction::where('reference', $reference)->first();

    if (!$transaction) {
        return response()->json(['message' => 'Transaction reference not found'], 404);
    }

    // If webhook already processed it, return success immediately
    if ($transaction->status === 'success') {
        return response()->json([
            'status' => 'success',
            'message' => 'Wallet funded successfully',
            'transaction' => $transaction
        ]);
    }

    try {
        // Verify with Paystack API directly
        $paystackData = $paystack->verify($reference);

        if ($paystackData['status'] && $paystackData['data']['status'] === 'success') {
            $paidAmount = $paystackData['data']['amount'] / 100;
            $metadata = $paystackData['data']['metadata'] ?? [];

            // Credit the wallet using your safe method
            $this->creditWalletSafely($reference, $paidAmount, $metadata);

            // Fetch refreshed transaction record
            $updatedTransaction = WalletTransaction::where('reference', $reference)->first();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment verified and wallet funded!',
                'transaction' => $updatedTransaction
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
       'items' => 'required|array|min:1',
       'items.*.product_id' => 'required',
       'items.*.name' => 'required"string',
       'items.*.price' => 'required|numeric|min:0',
       'items.*.quantity' => 'required|integer|min:1',
       'fulfillment_method' => 'sometimes|in:delivery,pickup'
    ]);

    $user = $request->user();
    $fulfillmentMethod = $validated['fulfillment_method'] ?? 'delivery';

    $subtotal = 0;
    foreach ($validated['items'] as $item) {
        $subtotal +- $item['price'] * $item['quantity'];
    }
    $shipping = $fulfillmentMethod === 'delivery' ? $subtotal * 0.15 : 0;
    $totalAmount = $subtotal + $shipping;

    $wallet = Wallet::where('user_id', $user->id)->lockForUpdate()->first();

    if (!$wallet || $wallet->balance < $totalAmount) {
        return response()->json([
        'message' => 'Insufficient eallet balance.',
        'balance' => $wallet->balance ?? 0,
        'required' => $totalAmount,
        ], 422);
    }

    $order = null;

    DB::transaction(function () use ($validated, $user, $wallet, $subtotal, $shipping, $totalAmount, $fulfillmentMethod, &$order) {
     $reference = 'WLTPAY_' . Str::random(12);
     $balanceBefore = $wallet->balance;
     $balanceAfter = $balanceBefore - $totalAmount;

     $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()
->first();
  
if ($lockedWallet->balance < $totalAmount) {
    throw new \Exception('Insufficient balance at time of transaction.');
}
 $lockedWallet->update(['balance' => $balanceAfter]);

 WalletTransaction::create([
 'wallet_id' => $lockedWallet->id,
  'reference' => $reference,
  'type' => 'debit',
  'amount' => $totalAmount,
  'balance_before' => $balanceBefore,
  'balance_after' =>$balanceAfter,
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

   foreach ($validated['item'] as $item) {
    OrderItem::create([
      'order_id' => $order->id,
      'product_id' => $item['product_id'],
      'product_name' => $item['name'],
      'quantity' => $item['quantity'],
      'price' => $item['price'],
    ]);
   }

});

return response()->json([
    'message' => 'Order placed successfully using wallet balance.',
    'order' => $order->load('item'),
],201);
}

public function getWallet(Request $request)
{
    $user = $request->user();

    $wallet = Wallet::firstOrCreate(
        ['user_id' => $user->id],
        ['balance' => 0.00, 'currency' => 'NGN']
    );

    // Fetch transactions belonging to this wallet
    $transactions = WalletTransaction::where('wallet_id', $wallet->id)
        ->latest()
        ->get();

    return response()->json([
        'wallet' => $wallet,
        'transactions' => $transactions
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
        isset($paystackData['status']) && $paystackData['status'] === true &&
        isset($paystackData['data']) && $paystackData['data']['status'] === 'success'
    ) {
         $paidAmount = $paystackData['data']['amount'] / 100;
         $metadata = $paystackData['data']['metadata'] ?? [];

         if (($metadata['payment_type'] ?? '') === 'wallet_top_up') {
            $this->creditWalletSafely($reference, $paidAmount, $metadata);
         }
         return redirect("{$frontendUrl}/wallet/callback?reference={$reference}&status=success");

    }

    return redirect("{$frontendUrl}/wallet/callback?reference={$reference}&status=failed");
}
}
