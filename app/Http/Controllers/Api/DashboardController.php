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
        $dateFrom = request('date_from');
        $dateTo = request('date_to');

        // ── Summary Stats ──────────────────────────────────
        $totalRevenueQuery = Order::query();
        $successRevenueQuery = Order::where('status', 'DELIVERED');
        $cancelledRevenueQuery = Order::where('status', 'CANCELLED');
        $totalOrdersQuery = Order::query();
        $totalUsersQuery = User::whereIn('role', ['customer', 'b2b']);

        $pendingOrdersQuery = Order::where('status', 'PENDING');
        $processingOrdersQuery = Order::where('status', 'PROCESSING');
        $deliveredOrdersQuery = Order::where('status', 'DELIVERED');
        $cancelledOrdersQuery = Order::where('status', 'CANCELLED');

        // Apply date filters if present
        if ($dateFrom) {
            $totalRevenueQuery->whereDate('created_at', '>=', $dateFrom);
            $successRevenueQuery->whereDate('created_at', '>=', $dateFrom);
            $cancelledRevenueQuery->whereDate('created_at', '>=', $dateFrom);
            $totalOrdersQuery->whereDate('created_at', '>=', $dateFrom);
            $totalUsersQuery->whereDate('created_at', '>=', $dateFrom);
            $pendingOrdersQuery->whereDate('created_at', '>=', $dateFrom);
            $processingOrdersQuery->whereDate('created_at', '>=', $dateFrom);
            $deliveredOrdersQuery->whereDate('created_at', '>=', $dateFrom);
            $cancelledOrdersQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $totalRevenueQuery->whereDate('created_at', '<=', $dateTo);
            $successRevenueQuery->whereDate('created_at', '<=', $dateTo);
            $cancelledRevenueQuery->whereDate('created_at', '<=', $dateTo);
            $totalOrdersQuery->whereDate('created_at', '<=', $dateTo);
            $totalUsersQuery->whereDate('created_at', '<=', $dateTo);
            $pendingOrdersQuery->whereDate('created_at', '<=', $dateTo);
            $processingOrdersQuery->whereDate('created_at', '<=', $dateTo);
            $deliveredOrdersQuery->whereDate('created_at', '<=', $dateTo);
            $cancelledOrdersQuery->whereDate('created_at', '<=', $dateTo);
        }

        $totalRevenue = $totalRevenueQuery->sum('total_amount');
        $successRevenue = $successRevenueQuery->sum('total_amount');
        $cancelledRevenue = $cancelledRevenueQuery->sum('total_amount');
        $totalOrders = $totalOrdersQuery->count();
        $totalUsers = $totalUsersQuery->count();

        $pendingOrders = $pendingOrdersQuery->count();
        $processingOrders = $processingOrdersQuery->count();
        $deliveredOrders = $deliveredOrdersQuery->count();
        $cancelledOrders = $cancelledOrdersQuery->count();

        // ── Success rate ────────────────────────────────────
        $successRate = $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 1) : 0;

        // ── Sales for chart ──────────────────────────────────
        $dailySales = [];
        if ($dateFrom && $dateTo) {
            $start = Carbon::parse($dateFrom);
            $end = Carbon::parse($dateTo);
            $diffInDays = $start->diffInDays($end);

            if ($diffInDays <= 31) {
                // If the selected range is 31 days or less, show each day's sales
                for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
                    $dayTotal = Order::whereDate('created_at', $date)->sum('total_amount');
                    $dailySales[] = [
                        'label' => $date->format('d M'),
                        'value' => (float) $dayTotal,
                    ];
                }
            } else {
                // If longer, group by weeks in the selected range
                $weeks = ceil($diffInDays / 7);
                for ($w = 0; $w < $weeks; $w++) {
                    $weekStart = $start->copy()->addWeeks($w)->startOfWeek();
                    $weekEnd = $start->copy()->addWeeks($w)->endOfWeek();
                    
                    // Cap at selected end date
                    if ($weekStart->gt($end)) break;
                    if ($weekEnd->gt($end)) $weekEnd = $end;

                    $weekTotal = Order::whereBetween('created_at', [$weekStart, $weekEnd])->sum('total_amount');
                    $dailySales[] = [
                        'label' => 'W' . ($w + 1) . ' (' . $weekStart->format('d/m') . ')',
                        'value' => (float) $weekTotal,
                    ];
                }
            }
        } else {
            // Default: last 7 days
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $dayTotal = Order::whereDate('created_at', $date)->sum('total_amount');
                $dayLabel = $date->format('D'); // Mon, Tue, ...
                $dailySales[] = [
                    'label' => $dayLabel,
                    'value' => (float) $dayTotal,
                ];
            }
        }

        // Monthly (last 4 weeks) - can follow similar logic
        $weeklySales = [];
        if ($dateFrom && $dateTo) {
            $start = Carbon::parse($dateFrom);
            $end = Carbon::parse($dateTo);
            $diffInWeeks = ceil($start->diffInDays($end) / 7);
            
            // Generate up to 6 intervals
            $intervals = min(6, max(1, $diffInWeeks));
            for ($w = 0; $w < $intervals; $w++) {
                $segmentStart = $start->copy()->addDays(floor($w * ($start->diffInDays($end) / $intervals)));
                $segmentEnd = $start->copy()->addDays(floor(($w + 1) * ($start->diffInDays($end) / $intervals)));
                if ($segmentEnd->gt($end)) $segmentEnd = $end;

                $segmentTotal = Order::whereBetween('created_at', [$segmentStart, $segmentEnd])->sum('total_amount');
                $weeklySales[] = [
                    'label' => $segmentStart->format('d/m') . '-' . $segmentEnd->format('d/m'),
                    'value' => (float) $segmentTotal,
                ];
            }
        } else {
            for ($w = 3; $w >= 0; $w--) {
                $weekStart = Carbon::today()->subWeeks($w)->startOfWeek();
                $weekEnd = Carbon::today()->subWeeks($w)->endOfWeek();
                $weekTotal = Order::whereBetween('created_at', [$weekStart, $weekEnd])->sum('total_amount');
                $weeklySales[] = [
                    'label' => 'W' . (4 - $w),
                    'value' => (float) $weekTotal,
                ];
            }
        }

        // Chart summary
        $totalWeekRevenue = array_sum(array_column($dailySales, 'value'));
        $avgDaily = count($dailySales) > 0 ? round($totalWeekRevenue / count($dailySales)) : 0;
        $peakDay = !empty($dailySales)
            ? collect($dailySales)->sortByDesc('value')->first()
            : ['label' => '-', 'value' => 0];

        // ── Recent Orders ───────────────────────────────────
        $recentOrdersQuery = Order::with('user');
        if ($dateFrom) {
            $recentOrdersQuery->whereDate('created_at', '>=', $dateFrom);
        }
        if ($dateTo) {
            $recentOrdersQuery->whereDate('created_at', '<=', $dateTo);
        }

        $recentOrders = $recentOrdersQuery->orderBy('created_at', 'desc')
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
                    'success_revenue' => (float) $successRevenue,
                    'cancelled_revenue' => (float) $cancelledRevenue,
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
