<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboardStats()
    {
        return response()->json([
            'total-orders' => Order::count(),
            'pending_orders'=> Order::where('status', 'pending')->count(),
            'total_revenue' => Order::where('status', 'completed')->sum('total_amount'),
            'total_products' => Product::count(),
            'low_stock_products' => Product::where('stock', '<', 5)->count(),
            'total_users' => User::count(),
        ]); 
    }
}
