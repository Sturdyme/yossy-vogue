<?php

namespace App\Jobs;

use App\Mail\UserDataExportMail;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ExportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $userId;

    public function __construct(int $userId)
    {
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $user = User::with(['orders.items', 'payments'])->find($this->userId);

        if (!$user) {
            return;
        }

        $exportData = [
            'profile' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'address' => $user->address,
                'notifications' => $user->notifications,
                'privacy' => $user->privacy,
                'account_created' => $user->created_at,
            ],
            'orders' => $user->orders->map(function ($order) {
                return [
                    'reference' => $order->reference,
                    'status' => $order->status,
                    'subtotal' => $order->subtotal,
                    'shipping' => $order->shipping,
                    'total_amount' => $order->total_amount,
                    'placed_at' => $order->created_at,
                    'items' => $order->items->map(function ($item) {
                        return [
                            'product_name' => $item->product_name,
                            'quantity' => $item->quantity,
                            'price' => $item->price,
                        ];
                    }),
                ];
            }),
            'payments' => $user->payments->map(function ($payment) {
                return [
                    'reference' => $payment->reference,
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'date' => $payment->created_at,
                ];
            }),
            'exported_at' => now()->toIso8601String(),
        ];

        $json = json_encode($exportData, JSON_PRETTY_PRINT);

        Mail::to($user->email)->send(new UserDataExportMail($user->name, $json));
    }
}