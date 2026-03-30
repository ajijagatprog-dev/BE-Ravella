<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\B2bStatusUpdateMail;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller
{
    // GET: /api/admin/users
    public function index(Request $request)
    {
        $query = User::whereIn('role', ['customer', 'b2b']);

        // Role filter for tabs
        if ($request->has('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Search
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('email', 'like', '%' . $searchTerm . '%')
                  ->orWhere('company_name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('npwp', 'like', '%' . $searchTerm . '%');
            });
        }

        // Include latest order for "Last Transaction" column
        $query->with(['orders' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(1);
        }]);

        $users = $query->latest()->paginate($request->get('limit', 10));

        // Transform data for frontend
        $users->getCollection()->transform(function ($user) {
            $latestOrder = $user->orders->first();

            // Determine user type label
            $type = $user->role === 'b2b' ? 'B2B Partner' : 'Retail';

            // Determine status
            if ($user->role === 'b2b') {
                $statusMap = [
                    'pending' => 'Pending Review',
                    'approved' => 'Active',
                    'rejected' => 'Inactive',
                ];
                $status = $statusMap[$user->b2b_status] ?? 'Inactive';
            } else {
                $status = 'Active';
            }

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'company' => $user->company_name,
                'npwp' => $user->npwp,
                'type' => $type,
                'status' => $status,
                'role' => $user->role,
                'b2b_status' => $user->b2b_status,
                'loyalty_points' => $user->loyalty_points,
                'created_at' => $user->created_at,
                'lastTransaction' => $latestOrder ? [
                    'amount' => 'Rp ' . number_format($latestOrder->total_amount, 0, ',', '.'),
                    'date' => $latestOrder->created_at->format('d M Y'),
                ] : null,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $users
        ]);
    }

    // GET: /api/admin/users/stats
    public function getUserStats()
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $startOfLastMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfLastMonth = $now->copy()->subMonth()->endOfMonth();

        // Total non-admin users
        $totalUsers = User::whereIn('role', ['customer', 'b2b'])->count();

        $newThisMonth = User::whereIn('role', ['customer', 'b2b'])
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        $newLastMonth = User::whereIn('role', ['customer', 'b2b'])
            ->whereBetween('created_at', [$startOfLastMonth, $endOfLastMonth])
            ->count();

        $usersTrend = $newLastMonth > 0
            ? round((($newThisMonth - $newLastMonth) / $newLastMonth) * 100, 1)
            : ($newThisMonth > 0 ? 100 : 0);

        // B2B Partners (approved)
        $b2bPartners = User::where('role', 'b2b')->where('b2b_status', 'approved')->count();

        $b2bNewThisMonth = User::where('role', 'b2b')
            ->where('b2b_status', 'approved')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        // Pending verifications
        $pendingVerifications = User::where('role', 'b2b')->where('b2b_status', 'pending')->count();

        // Retail customers
        $retailCustomers = User::where('role', 'customer')->count();

        $retailNewThisMonth = User::where('role', 'customer')
            ->where('created_at', '>=', $startOfMonth)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_users' => $totalUsers,
                'users_trend' => $usersTrend,
                'new_this_month' => $newThisMonth,
                'b2b_partners' => $b2bPartners,
                'b2b_new_this_month' => $b2bNewThisMonth,
                'pending_verifications' => $pendingVerifications,
                'retail_customers' => $retailCustomers,
                'retail_new_this_month' => $retailNewThisMonth,
            ]
        ]);
    }

    // PUT: /api/admin/users/{id}
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validated = $request->validate([
            'b2b_status' => 'sometimes|string|in:pending,approved,rejected',
            'role' => 'sometimes|string|in:customer,b2b',
            'name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|nullable|string|max:20',
        ]);

        $user->update($validated);

        // Send email notification to B2B user when status changes to approved or rejected
        if (isset($validated['b2b_status']) && in_array($validated['b2b_status'], ['approved', 'rejected'])) {
            try {
                Mail::to($user->email)->send(new B2bStatusUpdateMail($user, $validated['b2b_status']));
            } catch (\Exception $e) {
                Log::error('Failed to send B2B status update email to ' . $user->email . ': ' . $e->getMessage());
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'User updated successfully',
            'data' => $user
        ]);
    }

    // GET: /api/admin/users/{id}
    public function getUserDetail($id)
    {
        $user = User::findOrFail($id);

        $orders = Order::where('user_id', $id)
            ->with('items')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'status' => $order->status,
                    'payment_method' => $order->payment_method,
                    'created_at' => $order->created_at->format('d M Y H:i'),
                    'items_count' => $order->items->count(),
                ];
            });

        $totalSpent = Order::where('user_id', $id)
            ->where('status', 'DELIVERED')
            ->sum('total_amount');

        $totalOrders = Order::where('user_id', $id)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone_number' => $user->phone_number,
                    'role' => $user->role,
                    'company_name' => $user->company_name,
                    'npwp' => $user->npwp,
                    'b2b_status' => $user->b2b_status,
                    'address' => $user->address,
                    'loyalty_points' => $user->loyalty_points,
                    'created_at' => $user->created_at->format('d M Y H:i'),
                ],
                'stats' => [
                    'total_orders' => $totalOrders,
                    'total_spent' => $totalSpent,
                ],
                'recent_orders' => $orders,
            ]
        ]);
    }
}
