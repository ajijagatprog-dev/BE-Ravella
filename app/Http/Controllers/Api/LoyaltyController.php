<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\LoyaltySetting;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    // GET: /api/admin/loyalty/settings
    public function getSettings()
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'earning_multiplier' => (int) LoyaltySetting::getValue('earning_multiplier', '10'),
                'redemption_value' => (int) LoyaltySetting::getValue('redemption_value', '5'),
                'point_expiration' => (int) LoyaltySetting::getValue('point_expiration', '12'),
            ]
        ]);
    }

    // PUT: /api/admin/loyalty/settings
    public function updateSettings(Request $request)
    {
        $validated = $request->validate([
            'earning_multiplier' => 'sometimes|integer|min:1',
            'redemption_value' => 'sometimes|integer|min:1',
            'point_expiration' => 'sometimes|integer|min:1',
        ]);

        foreach ($validated as $key => $value) {
            LoyaltySetting::setValue($key, (string) $value);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Loyalty settings updated successfully',
            'data' => [
                'earning_multiplier' => (int) LoyaltySetting::getValue('earning_multiplier', '10'),
                'redemption_value' => (int) LoyaltySetting::getValue('redemption_value', '5'),
                'point_expiration' => (int) LoyaltySetting::getValue('point_expiration', '12'),
            ]
        ]);
    }

    // GET: /api/admin/loyalty/tiers
    public function getTiers()
    {
        $tiers = json_decode(LoyaltySetting::getValue('tiers', '[]'), true);
        return response()->json(['status' => 'success', 'data' => $tiers]);
    }

    // PUT: /api/admin/loyalty/tiers
    public function updateTiers(Request $request)
    {
        $validated = $request->validate([
            'tiers' => 'required|array|min:1',
            'tiers.*.name' => 'required|string',
            'tiers.*.min' => 'required|integer|min:0',
            'tiers.*.max' => 'nullable|integer',
            'tiers.*.perks' => 'required|array',
        ]);

        LoyaltySetting::setValue('tiers', json_encode($validated['tiers']));

        return response()->json([
            'status' => 'success',
            'message' => 'Tiers updated successfully',
            'data' => $validated['tiers'],
        ]);
    }

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
        $tiers = json_decode(LoyaltySetting::getValue('tiers', '[]'), true);
        // Sort tiers by min spend descending to find highest matching tier
        usort($tiers, fn($a, $b) => ($b['min'] ?? 0) - ($a['min'] ?? 0));

        foreach ($tiers as $tier) {
            if ($totalSpent >= ($tier['min'] ?? 0)) {
                $benefits = array_map(fn($perk) => ['label' => $perk, 'desc' => $perk], $tier['perks'] ?? []);
                return [
                    'name' => strtoupper($tier['name']),
                    'benefits' => $benefits,
                ];
            }
        }

        return [
            'name' => 'BASIC',
            'benefits' => [['label' => 'Member', 'desc' => 'Basic membership']],
        ];
    }

    private function calculateTierProgress(float $totalSpent): array
    {
        $tiers = json_decode(LoyaltySetting::getValue('tiers', '[]'), true);
        usort($tiers, fn($a, $b) => ($a['min'] ?? 0) - ($b['min'] ?? 0));

        // Find current tier index
        $currentIndex = 0;
        for ($i = count($tiers) - 1; $i >= 0; $i--) {
            if ($totalSpent >= ($tiers[$i]['min'] ?? 0)) {
                $currentIndex = $i;
                break;
            }
        }

        // If at highest tier
        if ($currentIndex >= count($tiers) - 1) {
            return ['percent' => 100, 'next_tier' => 'MAX'];
        }

        $currentMin = $tiers[$currentIndex]['min'] ?? 0;
        $nextMin = $tiers[$currentIndex + 1]['min'] ?? 0;
        $nextTierName = $tiers[$currentIndex + 1]['name'] ?? 'Next';
        $progress = $nextMin > $currentMin ? (($totalSpent - $currentMin) / ($nextMin - $currentMin)) * 100 : 0;

        return ['percent' => round(min($progress, 100), 0), 'next_tier' => $nextTierName];
    }
}
