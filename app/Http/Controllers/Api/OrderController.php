<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show($orderId)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $order = Order::with('items')
            ->where('user_id', $user->id)
            ->where(function ($query) use ($orderId) {
                $query->whereKey($orderId)
                    ->orWhere('reference', $orderId);
            })
            ->first();

        if (!$order) {
            return response()->json([
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json($order);
    }

    public function userOrders()
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $orders = Order::with('items')
        ->where('user_id', $user->id)
        ->latest()
        ->get();

        return response()->json([
            'orders' => $orders
        ]);
    }

    public function allOrders()
    {
        return Order::with(['items', 'user'])->latest()->paginate(20);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,shipped,completed,cancelled',
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json($order->load('items', 'user'));
    }
}