<?php

namespace App\Http\Controllers;

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
}
