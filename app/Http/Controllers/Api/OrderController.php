<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Carbon\Carbon;

class OrderController extends Controller
{
    // GET: /api/customer/orders
    public function getUserOrders(Request $request)
    {
        $orders = $request->user()->orders()->with('items.product')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    // GET: /api/customer/orders/{order_number}
    public function getOrderDetail(Request $request, $order_number)
    {
        $order = $request->user()->orders()->with('items.product')->where('order_number', $order_number)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    // POST: /api/customer/orders
    public function createOrder(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'shipping_address_id' => 'required|exists:addresses,id',
            'payment_method' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
        ]);

        // Get shipping address details to snapshot
        $address = $user->addresses()->where('id', $validated['shipping_address_id'])->firstOrFail();

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }

        // Add shipping cost (Free if > 500.000)
        $shippingFee = $totalAmount > 500000 ? 0 : 25000;
        $totalAmount += $shippingFee;

        $order = $user->orders()->create([
            'order_number' => 'RH-' . strtoupper(uniqid()),
            'total_amount' => $totalAmount,
            'status' => 'PENDING',
            'payment_method' => $validated['payment_method'],
            'shipping_address' => json_encode([
                'recipient_name' => $address->recipient_name,
                'phone_number' => $address->phone_number,
                'full_address' => $address->full_address,
                'city' => $address->city,
                'province' => $address->province,
                'postal_code' => $address->postal_code,
            ])
        ]);

        foreach ($validated['items'] as $item) {
            $order->items()->create([
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'price' => $item['price']
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Order created successfully',
            'data' => $order
        ], 201);
    }

    // --- ADMIN METHODS ---

    // GET: /api/admin/orders
    public function getAllOrders(Request $request)
    {
        // Admin user validation should ideally be middleware, assuming sanctum user is admin
        $orders = Order::with(['user', 'items.product'])->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ]);
    }

    // GET: /api/admin/orders/{order_number}
    public function getAdminOrderDetail(Request $request, $order_number)
    {
        $order = Order::with(['user', 'items.product'])->where('order_number', $order_number)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $order
        ]);
    }

    // PUT: /api/admin/orders/{order_number}/status
    public function updateOrderStatus(Request $request, $order_number)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:PENDING,PROCESSING,SHIPPED,DELIVERED,CANCELLED'
        ]);

        $order = Order::where('order_number', $order_number)->firstOrFail();
        $previousStatus = $order->status;
        $order->update(['status' => $validated['status']]);

        // Award loyalty points when order is delivered (uses dynamic multiplier)
        if ($validated['status'] === 'DELIVERED' && $previousStatus !== 'DELIVERED') {
            $multiplier = (int) \App\Models\LoyaltySetting::getValue('earning_multiplier', '10');
            $pointsToAward = max(1, floor($order->total_amount / 10000) * $multiplier);
            $user = \App\Models\User::find($order->user_id);

            if ($user) {
                // Record transaction
                \App\Models\LoyaltyTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'earn',
                    'points' => $pointsToAward,
                    'description' => "Purchase #{$order->order_number}",
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);

                // Update user's balance
                $user->increment('loyalty_points', $pointsToAward);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Order status updated successfully',
            'data' => $order
        ]);
    }

    // GET: /api/admin/orders/stats
    public function getOrderStats()
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();
        $today = $now->copy()->startOfDay();
        $yesterday = $now->copy()->subDay()->startOfDay();
        $endOfYesterday = $now->copy()->subDay()->endOfDay();

        // --- Total Pendapatan (Revenue from DELIVERED orders) ---
        $totalRevenue = Order::where('status', 'DELIVERED')->sum('total_amount');

        $revenueThisMonth = Order::where('status', 'DELIVERED')
            ->where('created_at', '>=', $startOfMonth)
            ->sum('total_amount');

        $revenueLastMonth = Order::where('status', 'DELIVERED')
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->sum('total_amount');

        $revenueTrend = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : ($revenueThisMonth > 0 ? 100 : 0);

        // --- Pesanan Aktif (Active orders: PENDING, PROCESSING, SHIPPED) ---
        $activeOrders = Order::whereIn('status', ['PENDING', 'PROCESSING', 'SHIPPED'])->count();

        $activeToday = Order::whereIn('status', ['PENDING', 'PROCESSING', 'SHIPPED'])
            ->where('created_at', '>=', $today)
            ->count();

        $activeYesterday = Order::whereIn('status', ['PENDING', 'PROCESSING', 'SHIPPED'])
            ->whereBetween('created_at', [$yesterday, $endOfYesterday])
            ->count();

        $activeTrend = $activeYesterday > 0
            ? round((($activeToday - $activeYesterday) / $activeYesterday) * 100, 1)
            : ($activeToday > 0 ? 100 : 0);

        // --- Pengiriman Tertunda (Pending shipments = PENDING status) ---
        $pendingShipments = Order::where('status', 'PENDING')->count();

        $startOfWeek = $now->copy()->startOfWeek(Carbon::MONDAY);
        $startOfLastWeek = $now->copy()->subWeek()->startOfWeek(Carbon::MONDAY);
        $endOfLastWeek = $now->copy()->subWeek()->endOfWeek(Carbon::SUNDAY);

        $pendingThisWeek = Order::where('status', 'PENDING')
            ->where('created_at', '>=', $startOfWeek)
            ->count();

        $pendingLastWeek = Order::where('status', 'PENDING')
            ->whereBetween('created_at', [$startOfLastWeek, $endOfLastWeek])
            ->count();

        $pendingTrend = $pendingLastWeek > 0
            ? round((($pendingThisWeek - $pendingLastWeek) / $pendingLastWeek) * 100, 1)
            : ($pendingThisWeek > 0 ? 100 : 0);

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_revenue' => $totalRevenue,
                'revenue_trend' => $revenueTrend,
                'active_orders' => $activeOrders,
                'active_trend' => $activeTrend,
                'pending_shipments' => $pendingShipments,
                'pending_trend' => $pendingTrend,
            ]
        ]);
    }
}
