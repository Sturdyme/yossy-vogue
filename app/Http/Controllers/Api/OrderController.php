<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function show($order_id)
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        $order = Order::with('items')
            ->where('order_id', $order_id)
            ->where('user_id', $user->id)
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
}