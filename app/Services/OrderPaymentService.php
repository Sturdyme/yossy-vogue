<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderPaymentService
{
    /**
     * Marks a card payment and its order as complete, and reduces stock.
     * Safe to call repeatedly (webhook, callback and verify can all call it for the same reference):
     * the payment row is locked and its status is saved, so only the first call does the work.
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

            $payment->update(['status' => 'success']);

            if (!$payment->order_id) {
                return true;
            }

            $completed = Order::where('id', $payment->order_id)
                ->where('status', 'pending')
                ->update(['status' => 'completed']);

            // Stock is reduced only by the call that actually moved the order to "completed".
            if ($completed === 1) {
                $this->reduceStock((int) $payment->order_id);
            } else {
                Log::warning('Payment succeeded but its order was not pending', [
                    'reference' => $reference,
                    'order_id' => $payment->order_id,
                ]);
            }

            return true;
        });
    }

    private function reduceStock(int $orderId): void
    {
        $wanted = OrderItem::where('order_id', $orderId)
            ->get(['product_id', 'quantity'])
            ->groupBy('product_id')
            ->map(fn ($lines) => (int) $lines->sum('quantity'));

        if ($wanted->isEmpty()) {
            return;
        }

        // Lock the product rows so two payments finishing at once cannot read the same stock.
        $products = Product::whereIn('id', $wanted->keys())
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($wanted as $productId => $quantity) {
            $product = $products->get($productId);

            if (!$product) {
                Log::warning('Paid order contains a product that no longer exists', [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                ]);

                continue;
            }

            if ((int) $product->stock < $quantity) {
                // The customer has already paid, so the order is NOT rejected: it needs a human decision.
                Log::warning('Order oversold: payment already taken, stock set to 0', [
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'ordered' => $quantity,
                    'in_stock' => (int) $product->stock,
                ]);
            }

            $product->forceFill(['stock' => max(0, (int) $product->stock - $quantity)])->save();
        }
    }
}
