<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    // POST: /api/payments/webhook
    public function simulateWebhook(Request $request)
    {
        $validated = $request->validate([
            'order_number' => 'required|string',
            'status' => 'required|in:success,failed,pending'
        ]);

        $order = Order::where('order_number', $validated['order_number'])->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        if ($validated['status'] === 'success') {
            $order->status = 'PROCESSING';
            $order->payment_token = 'sim_token_' . uniqid();

            // Reward loyalty points
            $pointsEarned = floor($order->total_amount / 10000);
            $user = $order->user;
            if ($user && isset($user->loyalty_points)) {
                $user->loyalty_points += $pointsEarned;
                $user->save();
            }

        } else if ($validated['status'] === 'failed') {
            $order->status = 'CANCELLED';
        }

        $order->save();

        Log::info("Simulated Payment Webhook received for order: " . $order->order_number . " with status: " . $validated['status']);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment status updated via simulated webhook',
            'data' => [
                'order_number' => $order->order_number,
                'new_status' => $order->status
            ]
        ]);
    }
}
