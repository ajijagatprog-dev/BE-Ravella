<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    // GET: /api/admin/loyalty
    public function getAdminLoyaltyData()
    {
        // Get all non-admin users with loyalty info
        $users = User::whereIn('role', ['customer', 'b2b'])->get();

        $customers = $users->map(function ($user) {
            $totalSpent = Order::where('user_id', $user->id)
                ->where('status', 'DELIVERED')
                ->sum('total_amount');
            $tier = $this->calculateTier($totalSpent);
            $recentTx = LoyaltyTransaction::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(fn($tx) => [
                    'id' => $tx->id,
                    'description' => $tx->description,
                    'points' => $tx->type === 'earn' ? $tx->points : -$tx->points,
                    'date' => $tx->created_at->format('d M Y'),
                    'type' => $tx->type,
                ]);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tier' => $tier['name'],
                'points' => $user->loyalty_points ?? 0,
                'total_spent' => $totalSpent,
                'total_orders' => Order::where('user_id', $user->id)->count(),
                'member_since' => $user->created_at->format('M Y'),
                'recent_transactions' => $recentTx,
            ];
        });

        // Stats summary
        $totalPoints = $users->sum('loyalty_points');
        $totalTransactions = LoyaltyTransaction::count();
        $pointsEarned = LoyaltyTransaction::where('type', 'earn')->sum('points');
        $pointsRedeemed = LoyaltyTransaction::where('type', 'redeem')->sum('points');

        return response()->json([
            'status' => 'success',
            'data' => [
                'customers' => $customers,
                'stats' => [
                    'total_members' => $users->count(),
                    'total_points_circulating' => $totalPoints,
                    'total_points_earned' => $pointsEarned,
                    'total_points_redeemed' => $pointsRedeemed,
                    'total_transactions' => $totalTransactions,
                ],
            ]
        ]);
    }

    // GET: /api/customer/loyalty
    public function getLoyaltyData(Request $request)
    {
        $user = $request->user();

        // Points balance
        $points = $user->loyalty_points ?? 0;

        // Tier calculation based on total lifetime spend (DELIVERED orders)
        $totalSpent = Order::where('user_id', $user->id)
            ->where('status', 'DELIVERED')
            ->sum('total_amount');

        $tier = $this->calculateTier($totalSpent);
        $tierProgress = $this->calculateTierProgress($totalSpent);

        // Recent transactions
        $transactions = LoyaltyTransaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($tx) {
                return [
                    'id' => (string) $tx->id,
                    'description' => $tx->description,
                    'points' => $tx->type === 'earn' ? $tx->points : -$tx->points,
                    'date' => $tx->created_at->format('M d, Y'),
                    'type' => $tx->type,
                ];
            });

        // Total orders count
        $totalOrders = Order::where('user_id', $user->id)->count();

        // Member since
        $memberSince = $user->created_at->format('M Y');

        return response()->json([
            'status' => 'success',
            'data' => [
                'points' => $points,
                'tier' => $tier['name'],
                'tier_progress' => $tierProgress['percent'],
                'next_tier' => $tierProgress['next_tier'],
                'member_since' => $memberSince,
                'total_spent' => $totalSpent,
                'total_orders' => $totalOrders,
                'benefits' => $tier['benefits'],
                'transactions' => $transactions,
            ]
        ]);
    }

    private function calculateTier(float $totalSpent): array
    {
        if ($totalSpent > 15000000) {
            return [
                'name' => 'PLATINUM',
                'benefits' => [
                    ['label' => 'Free Express Shipping', 'desc' => 'Gratis ongkir di semua pesanan'],
                    ['label' => 'VIP Early Access', 'desc' => 'Akses koleksi baru 48 jam lebih awal'],
                    ['label' => 'Personal Consultant', 'desc' => '1x sesi konsultasi per bulan'],
                    ['label' => 'Birthday Bonus', 'desc' => '1000 poin setiap tahun'],
                ],
            ];
        }
        if ($totalSpent > 5000000) {
            return [
                'name' => 'GOLD',
                'benefits' => [
                    ['label' => 'Free Standard Shipping', 'desc' => 'Gratis ongkir untuk pesanan > Rp 200.000'],
                    ['label' => 'Early Access', 'desc' => 'Akses koleksi baru 24 jam lebih awal'],
                    ['label' => 'Design Consultation', 'desc' => '1x 30 menit sesi per kuartal'],
                    ['label' => 'Birthday Bonus', 'desc' => '500 poin setiap tahun'],
                ],
            ];
        }
        return [
            'name' => 'BASIC',
            'benefits' => [
                ['label' => 'Free Shipping', 'desc' => 'Gratis ongkir untuk pesanan > Rp 500.000'],
                ['label' => 'Member Pricing', 'desc' => 'Akses harga khusus member'],
                ['label' => 'Birthday Bonus', 'desc' => '200 poin setiap tahun'],
                ['label' => 'Newsletter Previews', 'desc' => 'Info produk terbaru lebih awal'],
            ],
        ];
    }

    private function calculateTierProgress(float $totalSpent): array
    {
        if ($totalSpent > 15000000) {
            return ['percent' => 100, 'next_tier' => 'MAX'];
        }
        if ($totalSpent > 5000000) {
            $progress = (($totalSpent - 5000000) / (15000000 - 5000000)) * 100;
            return ['percent' => round($progress, 0), 'next_tier' => 'Platinum'];
        }
        $progress = ($totalSpent / 5000000) * 100;
        return ['percent' => round($progress, 0), 'next_tier' => 'Gold'];
    }
}
