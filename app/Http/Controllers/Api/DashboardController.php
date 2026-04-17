<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    // GET: /api/admin/dashboard
    public function index()
    {
        // ── Summary Stats ──────────────────────────────────
        $totalRevenue = Order::where('status', 'DELIVERED')->sum('total_amount');
        $totalOrders = Order::count();
        $totalUsers = User::whereIn('role', ['customer', 'b2b'])->count();

        $pendingOrders = Order::where('status', 'PENDING')->count();
        $processingOrders = Order::where('status', 'PROCESSING')->count();
        $deliveredOrders = Order::where('status', 'DELIVERED')->count();
        $cancelledOrders = Order::where('status', 'CANCELLED')->count();

        // ── Success rate ────────────────────────────────────
        $successRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0;

        // ── Recent 7 days sales for chart ───────────────────
        $dailySales = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayTotal = Order::whereDate('created_at', $date)->sum('total_amount');
            $dayLabel = $date->format('D'); // Mon, Tue, ...
            $dailySales[] = [
                'label' => $dayLabel,
                'value' => (float) $dayTotal,
            ];
        }

        // Monthly (last 4 weeks)
        $weeklySales = [];
        for ($w = 3; $w >= 0; $w--) {
            $weekStart = Carbon::today()->subWeeks($w)->startOfWeek();
            $weekEnd = Carbon::today()->subWeeks($w)->endOfWeek();
            $weekTotal = Order::whereBetween('created_at', [$weekStart, $weekEnd])->sum('total_amount');
            $weeklySales[] = [
                'label' => 'W' . (4 - $w),
                'value' => (float) $weekTotal,
            ];
        }

        // Chart summary
        $totalWeekRevenue = array_sum(array_column($dailySales, 'value'));
        $avgDaily = $totalWeekRevenue > 0 ? round($totalWeekRevenue / 7) : 0;
        $peakDay = !empty($dailySales)
            ? collect($dailySales)->sortByDesc('value')->first()
            : ['label' => '-', 'value' => 0];

        // ── Recent Orders ───────────────────────────────────
        $recentOrders = Order::with('user')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($order) {
                return [
                    'order_number' => $order->order_number,
                    'customer' => $order->user->name ?? 'Unknown',
                    'total' => $order->total_amount,
                    'status' => $order->status,
                    'time_ago' => $order->created_at->diffForHumans(),
                ];
            });

        // ── Low Stock Products ──────────────────────────────
        $lowStockCount = Product::where('stock', '<', 10)->where('stock', '>', 0)->count();
        $outOfStockCount = Product::where('stock', '<=', 0)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'stats' => [
                    'total_revenue' => (float) $totalRevenue,
                    'total_orders' => $totalOrders,
                    'total_users' => $totalUsers,
                    'pending_orders' => $pendingOrders,
                    'processing_orders' => $processingOrders,
                    'delivered_orders' => $deliveredOrders,
                    'cancelled_orders' => $cancelledOrders,
                    'success_rate' => $successRate,
                    'low_stock' => $lowStockCount,
                    'out_of_stock' => $outOfStockCount,
                ],
                'chart' => [
                    'daily' => $dailySales,
                    'weekly' => $weeklySales,
                    'summary' => [
                        'total' => $totalWeekRevenue,
                        'avg_daily' => $avgDaily,
                        'peak' => $peakDay,
                    ],
                ],
                'recent_orders' => $recentOrders,
            ],
        ]);
    }
}
