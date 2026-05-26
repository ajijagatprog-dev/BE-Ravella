<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoyaltyTransaction;
use App\Models\LoyaltySetting;
use App\Models\LoyaltyClaim;
use App\Models\Order;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
                'loyalty_enabled' => LoyaltySetting::getValue('loyalty_enabled', '1') === '1',
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
            'loyalty_enabled' => 'sometimes|boolean',
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
                'loyalty_enabled' => LoyaltySetting::getValue('loyalty_enabled', '1') === '1',
            ]
        ]);
    }

    // GET: /api/admin/loyalty/tiers
    public function getTiers()
    {
        $tiers = json_decode(LoyaltySetting::getValue('tiers', '[]'), true);
        return response()->json(['status' => 'success', 'data' => $tiers]);
    }

    // GET: /api/public/loyalty/tiers (no auth required — for homepage showcase)
    public function getPublicTiers()
    {
        $enabled = LoyaltySetting::getValue('loyalty_enabled', '1') === '1';
        if (!$enabled) {
            return response()->json(['status' => 'success', 'data' => []]);
        }
        $tiers = json_decode(LoyaltySetting::getValue('tiers', '[]'), true);
        return response()->json(['status' => 'success', 'data' => $tiers]);
    }

    // PUT: /api/admin/loyalty/tiers
    public function updateTiers(Request $request)
    {
        $validated = $request->validate([
            'tiers' => 'required|array|min:1',
            'tiers.*.name' => 'required|string',
            'tiers.*.label' => 'nullable|string',
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

    // GET: /api/customer/loyalty/rewards
    // Returns: active vouchers (as redeemable rewards) + tier perks
    public function getCustomerRewards(Request $request)
    {
        $user = $request->user();
        $enabled = LoyaltySetting::getValue('loyalty_enabled', '1') === '1';

        if (!$enabled) {
            return response()->json(['status' => 'success', 'data' => []]);
        }

        $userPoints = $user->loyalty_points ?? 0;

        // ── 1. Tier Perks (based on current tier) ──────────────────────────────
        $totalSpent = Order::where('user_id', $user->id)
            ->where('status', 'DELIVERED')
            ->sum('total_amount');

        $tierData = $this->calculateTier($totalSpent);
        $tierPerks = array_map(fn($perk) => [
            'id' => 'perk_' . md5($perk['label']),
            'title' => $perk['label'],
            'subtitle' => 'Tier Benefit',
            'description' => $perk['desc'],
            'points_required' => 0,
            'type' => 'perk',
            'canRedeem' => true,
        ], $tierData['benefits']);

        // ── 2. Active Vouchers (redeemable with points) ───────────────────────
        // Ambil voucher yang aktif dan belum expired
        $activeVouchers = Voucher::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->get();

        $redemptionValue = (int) LoyaltySetting::getValue('redemption_value', '5');

        $voucherRewards = $activeVouchers->map(function ($voucher) use ($redemptionValue, $userPoints) {
            // Calculate points needed to cover the voucher's fixed value
            // For percent vouchers: estimate points based on max_discount or a base value
            $voucherValue = $voucher->type === 'fixed'
                ? (float) $voucher->value
                : (float) ($voucher->max_discount ?? 50000);

            // Points needed = voucherValue / redemptionValue
            $pointsNeeded = $redemptionValue > 0
                ? (int) ceil($voucherValue / $redemptionValue)
                : 999999;

            $title = $voucher->type === 'percent'
                ? "{$voucher->value}% OFF"
                : 'Rp ' . number_format($voucher->value, 0, ',', '.');

            return [
                'id' => 'voucher_' . $voucher->id,
                'voucher_id' => $voucher->id,
                'voucher_code' => $voucher->code,
                'title' => $title,
                'subtitle' => $voucher->description ?? 'Voucher Diskon',
                'description' => $voucher->description ?? 'Tukarkan poin Anda dengan voucher diskon ini.',
                'points_required' => $pointsNeeded,
                'type' => 'voucher',
                'canRedeem' => true,
                'min_purchase' => (float) ($voucher->min_purchase ?? 0),
            ];
        })->values()->toArray();

        $rewards = array_merge($tierPerks, $voucherRewards);

        return response()->json([
            'status' => 'success',
            'data' => [
                'rewards' => $rewards,
                'user_points' => $userPoints,
            ]
        ]);
    }

    // POST: /api/customer/loyalty/redeem
    // Body: { voucher_id: int, points_to_spend: int }
    public function redeemReward(Request $request)
    {
        $enabled = LoyaltySetting::getValue('loyalty_enabled', '1') === '1';
        if (!$enabled) {
            return response()->json(['status' => 'error', 'message' => 'Sistem loyalty sedang tidak aktif.'], 403);
        }

        $validated = $request->validate([
            'voucher_id' => 'required|integer|exists:vouchers,id',
            'points_to_spend' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $userPoints = $user->loyalty_points ?? 0;

        if ($userPoints < $validated['points_to_spend']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Poin tidak cukup. Poin Anda: ' . $userPoints . ', dibutuhkan: ' . $validated['points_to_spend'],
            ], 422);
        }

        $voucher = Voucher::findOrFail($validated['voucher_id']);

        if (!$voucher->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Voucher sudah tidak aktif atau telah kadaluarsa.',
            ], 422);
        }

        // Check max_per_user
        if ($voucher->max_per_user) {
            $userUsageCount = LoyaltyTransaction::where('user_id', $user->id)
                ->where('type', 'redeem')
                ->where('reference_type', 'voucher')
                ->where('reference_id', $voucher->id)
                ->count();
            if ($userUsageCount >= $voucher->max_per_user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda sudah mencapai batas maksimum penukaran voucher ini.',
                ], 422);
            }
        }

        DB::transaction(function () use ($user, $validated, $voucher) {
            // Deduct points
            $user->decrement('loyalty_points', $validated['points_to_spend']);

            // Record transaction
            LoyaltyTransaction::create([
                'user_id' => $user->id,
                'type' => 'redeem',
                'points' => $validated['points_to_spend'],
                'description' => 'Penukaran poin untuk voucher ' . $voucher->code,
                'reference_type' => 'voucher',
                'reference_id' => $voucher->id,
            ]);
        });

        Log::info("User {$user->id} redeemed {$validated['points_to_spend']} points for voucher {$voucher->code}");

        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil! Voucher ' . $voucher->code . ' telah ditambahkan ke akun Anda.',
            'data' => [
                'voucher_code' => $voucher->code,
                'voucher_description' => $voucher->description,
                'points_spent' => $validated['points_to_spend'],
                'remaining_points' => ($user->loyalty_points ?? 0),
            ]
        ]);
    }

    // ─── GET: /api/customer/loyalty/claimable ────────────────────────────────────
    // Returns tier claimable rewards yang belum & sudah diklaim customer
    // ─────────────────────────────────────────────────────────────────────────────
    public function getClaimableRewards(Request $request)
    {
        $user = $request->user();
        $enabled = LoyaltySetting::getValue('loyalty_enabled', '1') === '1';
        if (!$enabled) {
            return response()->json(['status' => 'success', 'data' => ['claimable' => [], 'claimed' => []]]);
        }

        $totalSpent = Order::where('user_id', $user->id)
            ->where('status', 'DELIVERED')
            ->sum('total_amount');

        $tiers = json_decode(LoyaltySetting::getValue('tiers', '[]'), true);
        usort($tiers, fn($a, $b) => ($b['min'] ?? 0) - ($a['min'] ?? 0));

        $unlockedRewards = [];
        foreach ($tiers as $tier) {
            if ($totalSpent >= ($tier['min'] ?? 0)) {
                foreach ($tier['claimable_rewards'] ?? [] as $reward) {
                    if (!isset($reward['id'])) continue;
                    $reward['tier_name'] = $tier['name'];
                    $unlockedRewards[] = $reward;
                }
                break;
            }
        }

        if (empty($unlockedRewards)) {
            return response()->json(['status' => 'success', 'data' => ['claimable' => [], 'claimed' => []]]);
        }

        $claimedIds = LoyaltyClaim::where('user_id', $user->id)->pluck('reward_id')->toArray();

        $claimable = [];
        $claimed   = [];

        foreach ($unlockedRewards as $reward) {
            $rewardId  = $reward['id'];
            $isClaimed = in_array($rewardId, $claimedIds);

            $voucherLabel = null;
            if (($reward['type'] ?? '') === 'voucher_code' && isset($reward['voucher_id'])) {
                $v = Voucher::find($reward['voucher_id']);
                if ($v) {
                    $voucherLabel = $v->type === 'percent'
                        ? "Diskon {$v->value}%"
                        : 'Rp ' . number_format((float) $v->value, 0, ',', '.');
                }
            }

            $entry = [
                'id'            => $rewardId,
                'label'         => $reward['label'] ?? 'Tier Reward',
                'type'          => $reward['type'] ?? 'bonus_points',
                'tier_name'     => $reward['tier_name'],
                'points'        => $reward['points'] ?? null,
                'voucher_id'    => $reward['voucher_id'] ?? null,
                'voucher_label' => $voucherLabel,
                'one_time'      => $reward['one_time'] ?? true,
                'is_claimed'    => $isClaimed,
            ];

            if ($isClaimed) {
                $claim = LoyaltyClaim::where('user_id', $user->id)->where('reward_id', $rewardId)->first();
                $entry['claimed_value'] = $claim?->reward_value;
                $entry['claimed_at']    = $claim?->claimed_at?->format('d M Y');
                $claimed[] = $entry;
            } else {
                $claimable[] = $entry;
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => ['claimable' => $claimable, 'claimed' => $claimed],
        ]);
    }

    // ─── POST: /api/customer/loyalty/claim ──────────────────────────────────────
    // Body: { reward_id: string }
    // ─────────────────────────────────────────────────────────────────────────────
    public function claimReward(Request $request)
    {
        $enabled = LoyaltySetting::getValue('loyalty_enabled', '1') === '1';
        if (!$enabled) {
            return response()->json(['status' => 'error', 'message' => 'Sistem loyalty sedang tidak aktif.'], 403);
        }

        $validated = $request->validate(['reward_id' => 'required|string']);
        $user = $request->user();

        // Cek sudah pernah diklaim
        if (LoyaltyClaim::where('user_id', $user->id)->where('reward_id', $validated['reward_id'])->exists()) {
            return response()->json(['status' => 'error', 'message' => 'Reward ini sudah pernah Anda klaim.'], 422);
        }

        // Cari reward di config tier yang sesuai dengan total belanja user
        $totalSpent = Order::where('user_id', $user->id)->where('status', 'DELIVERED')->sum('total_amount');
        $tiers = json_decode(LoyaltySetting::getValue('tiers', '[]'), true);
        usort($tiers, fn($a, $b) => ($b['min'] ?? 0) - ($a['min'] ?? 0));

        $foundReward = null;
        $foundTier   = null;
        foreach ($tiers as $tier) {
            if ($totalSpent >= ($tier['min'] ?? 0)) {
                foreach ($tier['claimable_rewards'] ?? [] as $reward) {
                    if (($reward['id'] ?? '') === $validated['reward_id']) {
                        $foundReward = $reward;
                        $foundTier   = $tier['name'];
                        break 2;
                    }
                }
                break;
            }
        }

        if (!$foundReward) {
            return response()->json(['status' => 'error', 'message' => 'Reward tidak ditemukan atau Anda belum memenuhi syarat.'], 422);
        }

        $rewardType   = $foundReward['type'] ?? 'bonus_points';
        $rewardValue  = null;
        $message      = '';
        $responseData = [];

        DB::transaction(function () use ($user, $foundReward, $foundTier, $rewardType, $validated, &$rewardValue, &$message, &$responseData) {
            if ($rewardType === 'bonus_points') {
                $pts = (int) ($foundReward['points'] ?? 0);
                if ($pts <= 0) throw new \Exception('Jumlah poin reward tidak valid.');

                $user->increment('loyalty_points', $pts);
                LoyaltyTransaction::create([
                    'user_id'        => $user->id,
                    'type'           => 'earn',
                    'points'         => $pts,
                    'description'    => 'Tier Reward: ' . ($foundReward['label'] ?? $foundTier),
                    'reference_type' => 'loyalty_claim',
                    'reference_id'   => 0,
                ]);
                $rewardValue  = (string) $pts;
                $message      = "Selamat! {$pts} poin telah ditambahkan ke akun Anda.";
                $responseData = ['type' => 'bonus_points', 'points_awarded' => $pts, 'new_balance' => ($user->loyalty_points ?? 0)];

            } elseif ($rewardType === 'voucher_code') {
                $voucherId = $foundReward['voucher_id'] ?? null;
                if (!$voucherId) throw new \Exception('Konfigurasi voucher tidak lengkap.');

                $voucher = Voucher::find($voucherId);
                if (!$voucher || !$voucher->isValid()) {
                    throw new \Exception('Voucher tidak tersedia atau sudah kadaluarsa.');
                }

                // Buat kode unik untuk customer ini
                $uniqueCode = strtoupper($voucher->code . '_' . Str::random(4));
                Voucher::create([
                    'code'         => $uniqueCode,
                    'description'  => ($voucher->description ?? '') . ' (Loyalty Reward)',
                    'type'         => $voucher->type,
                    'value'        => $voucher->value,
                    'min_purchase' => $voucher->min_purchase,
                    'max_discount' => $voucher->max_discount,
                    'max_uses'     => 1,
                    'max_per_user' => 1,
                    'is_active'    => true,
                    'expires_at'   => now()->addDays(90),
                    'starts_at'    => now(),
                    'sku'          => null,
                ]);

                $rewardValue  = $uniqueCode;
                $message      = "Selamat! Voucher {$uniqueCode} berhasil diklaim. Gunakan saat checkout (berlaku 90 hari).";
                $responseData = [
                    'type'         => 'voucher_code',
                    'voucher_code' => $uniqueCode,
                    'description'  => $voucher->description,
                    'value'        => $voucher->type === 'percent'
                        ? "{$voucher->value}% OFF"
                        : 'Rp ' . number_format((float) $voucher->value, 0, ',', '.'),
                ];
            } else {
                throw new \Exception('Tipe reward tidak dikenali.');
            }

            LoyaltyClaim::create([
                'user_id'      => $user->id,
                'reward_id'    => $validated['reward_id'],
                'tier_name'    => $foundTier,
                'reward_type'  => $rewardType,
                'reward_value' => $rewardValue,
                'claimed_at'   => now(),
            ]);
        });

        Log::info("User {$user->id} claimed loyalty reward '{$validated['reward_id']}' ({$rewardType}): {$rewardValue}");

        return response()->json(['status' => 'success', 'message' => $message, 'data' => $responseData]);
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

    // POST: /api/admin/loyalty/sync-points
    // Retroactively award points for DELIVERED orders that have no loyalty transaction recorded.
    public function syncLoyaltyPoints()
    {
        $multiplier = (int) LoyaltySetting::getValue('earning_multiplier', '10');

        $deliveredOrders = Order::where('status', 'DELIVERED')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('loyalty_transactions')
                    ->whereColumn('loyalty_transactions.reference_id', 'orders.id')
                    ->where('loyalty_transactions.reference_type', 'order')
                    ->where('loyalty_transactions.type', 'earn');
            })
            ->with('user')
            ->get();

        $synced = 0;
        $skipped = 0;
        $details = [];

        foreach ($deliveredOrders as $order) {
            $user = $order->user;
            if (!$user) {
                $skipped++;
                continue;
            }

            $pointsToAward = max(1, floor($order->total_amount / 10000) * $multiplier);

            DB::transaction(function () use ($user, $order, $pointsToAward) {
                LoyaltyTransaction::create([
                    'user_id' => $user->id,
                    'type' => 'earn',
                    'points' => $pointsToAward,
                    'description' => "Sync poin - Purchase #{$order->order_number}",
                    'reference_type' => 'order',
                    'reference_id' => $order->id,
                ]);
                $user->increment('loyalty_points', $pointsToAward);
            });

            $details[] = [
                'order_number' => $order->order_number,
                'user' => $user->name,
                'amount' => $order->total_amount,
                'points_awarded' => $pointsToAward,
            ];
            $synced++;
        }

        Log::info("Loyalty sync completed: {$synced} orders synced, {$skipped} skipped.");

        return response()->json([
            'status' => 'success',
            'message' => "Sync selesai. {$synced} order berhasil disinkronkan, {$skipped} dilewati.",
            'data' => [
                'synced_count' => $synced,
                'skipped_count' => $skipped,
                'multiplier_used' => $multiplier,
                'details' => $details,
            ],
        ]);
    }
}
