<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    // GET: /api/admin/reports/sales
    public function salesReport(Request $request)
    {
        $period = $request->input('period', 'all');
        $dateFrom = $this->getDateFrom($period);

        // Per-product sales data
        $products = Product::all()->map(function ($p) use ($dateFrom) {
            $itemsQuery = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('order_items.product_id', $p->id);

            if ($dateFrom) {
                $itemsQuery->where('orders.created_at', '>=', $dateFrom);
            }

            $unitsSold = (clone $itemsQuery)->sum('order_items.quantity');
            $revenue = (clone $itemsQuery)->sum(DB::raw('order_items.price * order_items.quantity'));

            return [
                'product' => $p->name,
                'sku' => $p->sku ?? 'N/A',
                'category' => $p->category ?? 'General',
                'unitsSold' => (int) $unitsSold,
                'revenue' => (float) $revenue,
                'stock' => $p->stock ?? 0,
            ];
        });

        // Summary stats
        $totalRevenue = Order::where('status', 'DELIVERED')
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->sum('total_amount');

        $ordersCount = Order::when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))->count();
        $avgOrderValue = $ordersCount > 0 ? round($totalRevenue / $ordersCount) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'products' => $products,
                'summary' => [
                    ['label' => 'Total Revenue', 'value' => $totalRevenue, 'change' => 0, 'up' => true],
                    ['label' => 'Orders Count', 'value' => $ordersCount, 'change' => 0, 'up' => true],
                    ['label' => 'Avg. Order Value', 'value' => $avgOrderValue, 'change' => 0, 'up' => true],
                ],
            ]
        ]);
    }

    // GET: /api/admin/reports/customers
    public function customerReport(Request $request)
    {
        $users = User::whereIn('role', ['customer', 'b2b'])
            ->withCount('orders')
            ->get()
            ->map(function ($user) {
                $totalSpent = Order::where('user_id', $user->id)
                    ->where('status', 'DELIVERED')
                    ->sum('total_amount');
                $lastOrder = Order::where('user_id', $user->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                // Determine tier
                $tier = 'Basic';
                if ($totalSpent > 15000000) $tier = 'Platinum';
                elseif ($totalSpent > 5000000) $tier = 'Gold';

                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'orders' => $user->orders_count,
                    'totalSpent' => $totalSpent,
                    'lastOrder' => $lastOrder ? $lastOrder->created_at->format('M d, Y') : '—',
                    'status' => $user->orders_count > 0 ? 'active' : 'inactive',
                    'tier' => $tier,
                ];
            });

        $totalCustomers = $users->count();
        $activeCustomers = $users->where('status', 'active')->count();
        $avgOrders = $totalCustomers > 0 ? round($users->avg('orders'), 1) : 0;
        $totalSpentAll = $users->sum('totalSpent');
        $avgLtv = $totalCustomers > 0 ? round($totalSpentAll / $totalCustomers) : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'customers' => $users->values(),
                'summary' => [
                    ['label' => 'Total Customers', 'value' => $totalCustomers, 'change' => 0, 'up' => true],
                    ['label' => 'Active Customers', 'value' => $activeCustomers, 'change' => 0, 'up' => true],
                    ['label' => 'Avg. Orders / Customer', 'value' => $avgOrders, 'change' => 0, 'up' => true],
                    ['label' => 'Customer LTV', 'value' => $avgLtv, 'change' => 0, 'up' => true],
                ],
            ]
        ]);
    }

    // GET: /api/admin/reports/stock
    public function stockReport()
    {
        $products = Product::all()->map(fn($p) => [
            'product' => $p->name,
            'sku' => $p->sku ?? 'N/A',
            'category' => $p->category ?? 'General',
            'stock' => $p->stock ?? 0,
            'price' => $p->price ?? 0,
            'stockStatus' => ($p->stock ?? 0) <= 0 ? 'Out of Stock' : (($p->stock ?? 0) < 10 ? 'Low Stock' : 'In Stock'),
        ]);

        $inStock = $products->where('stockStatus', 'In Stock')->count();
        $lowStock = $products->where('stockStatus', 'Low Stock')->count();
        $outOfStock = $products->where('stockStatus', 'Out of Stock')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'products' => $products->values(),
                'summary' => [
                    ['label' => 'Total SKUs', 'value' => $products->count(), 'change' => 0, 'up' => true],
                    ['label' => 'In Stock', 'value' => $inStock, 'change' => 0, 'up' => true],
                    ['label' => 'Low Stock', 'value' => $lowStock, 'change' => 0, 'up' => $lowStock == 0],
                    ['label' => 'Out of Stock', 'value' => $outOfStock, 'change' => 0, 'up' => $outOfStock == 0],
                ],
            ]
        ]);
    }

    // GET: /api/admin/reports/transactions
    public function transactionReport(Request $request)
    {
        $period = $request->input('period', 'last_30');
        $dateFrom = $this->getDateFrom($period);
        $status = $request->input('status', 'all');

        $query = Order::with('user')
            ->when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))
            ->when($status !== 'all', fn($q) => $q->where('status', strtoupper($status)));

        $orders = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($o) => [
                'id' => $o->order_number,
                'customer' => $o->user->name ?? 'Unknown',
                'date' => $o->created_at->format('M d, Y'),
                'items' => $o->items()->count(),
                'amount' => $o->total_amount,
                'method' => $o->payment_method ?? 'N/A',
                'status' => ucfirst(strtolower($o->status)),
            ]);

        $allOrders = Order::when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom));
        $total = $allOrders->count();
        $completed = Order::when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))->where('status', 'DELIVERED')->count();
        $pending = Order::when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))->whereIn('status', ['PENDING', 'PROCESSING'])->count();
        $cancelled = Order::when($dateFrom, fn($q) => $q->where('created_at', '>=', $dateFrom))->where('status', 'CANCELLED')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'transactions' => $orders->values(),
                'summary' => [
                    ['label' => 'Total Transactions', 'value' => $total, 'change' => 0, 'up' => true],
                    ['label' => 'Completed', 'value' => $completed, 'change' => 0, 'up' => true],
                    ['label' => 'Pending / Processing', 'value' => $pending, 'change' => 0, 'up' => $pending == 0],
                    ['label' => 'Cancelled', 'value' => $cancelled, 'change' => 0, 'up' => $cancelled == 0],
                ],
            ]
        ]);
    }

    private function getDateFrom(string $period): ?string
    {
        return match($period) {
            'last_7' => now()->subDays(7)->toDateString(),
            'last_30' => now()->subDays(30)->toDateString(),
            'last_90' => now()->subDays(90)->toDateString(),
            'all' => null,
            default => now()->subDays(30)->toDateString(),
        };
    }
}
