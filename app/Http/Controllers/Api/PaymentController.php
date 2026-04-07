<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    /**
     * Handle Xendit Invoice Webhook Callback
     * This endpoint receives POST requests from Xendit when an invoice status changes.
     * Route: POST /api/payments/xendit/webhook
     */
    public function handleXenditWebhook(Request $request)
    {
        // Log the incoming webhook for debugging
        Log::info('Xendit Webhook Received', [
            'headers' => $request->headers->all(),
            'payload' => $request->all(),
        ]);

        // Verify the callback token (x-callback-token header)
        // In production, you should set this in Xendit dashboard and verify.
        // For development, we'll accept all callbacks.
        $callbackToken = $request->header('x-callback-token');
        $expectedToken = env('XENDIT_CALLBACK_TOKEN');
        
        if ($expectedToken && $callbackToken !== $expectedToken) {
            Log::warning('Xendit Webhook: Invalid callback token');
            return response()->json(['status' => 'error', 'message' => 'Invalid callback token'], 403);
        }

        $externalId = $request->input('external_id');
        $status = $request->input('status');
        $paymentMethod = $request->input('payment_method');
        $paymentChannel = $request->input('payment_channel');
        $paidAt = $request->input('paid_at');
        $xenditInvoiceId = $request->input('id');

        if (!$externalId || !$status) {
            Log::warning('Xendit Webhook: Missing external_id or status');
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        }

        // Find the order by order_number (external_id)
        $order = Order::where('order_number', $externalId)->first();

        if (!$order) {
            Log::warning("Xendit Webhook: Order not found for external_id: {$externalId}");
            return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        Log::info("Xendit Webhook: Processing status '{$status}' for order {$order->order_number}");

        switch (strtoupper($status)) {
            case 'PAID':
            case 'SETTLED':
                // Payment successful
                if ($order->status === 'PENDING') {
                    $order->update([
                        'status' => 'PROCESSING',
                        'payment_method' => $paymentMethod ?? $order->payment_method,
                        'payment_channel' => $paymentChannel,
                        'payment_token' => $xenditInvoiceId,
                        'paid_at' => $paidAt ? date('Y-m-d H:i:s', strtotime($paidAt)) : now(),
                    ]);

                    // Award loyalty points
                    $pointsEarned = floor($order->total_amount / 10000);
                    $user = $order->user;
                    if ($user && isset($user->loyalty_points)) {
                        $user->loyalty_points += $pointsEarned;
                        $user->save();
                    }

                    Log::info("Xendit Webhook: Order {$order->order_number} marked as PROCESSING. Payment via {$paymentChannel}.");
                }
                break;

            case 'EXPIRED':
                if ($order->status === 'PENDING') {
                    $order->update([
                        'status' => 'CANCELLED',
                    ]);
                    Log::info("Xendit Webhook: Order {$order->order_number} expired and cancelled.");
                }
                break;

            default:
                Log::info("Xendit Webhook: Unhandled status '{$status}' for order {$order->order_number}");
                break;
        }

        // Xendit expects a 200 response to confirm receipt
        return response()->json(['status' => 'success', 'message' => 'Webhook processed']);
    }

    /**
     * Get payment status for an order (for frontend polling)
     * Route: GET /api/payments/status/{order_number}
     */
    public function getPaymentStatus(Request $request, $order_number)
    {
        $order = Order::where('order_number', $order_number)->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'payment_method' => $order->payment_method,
                'payment_channel' => $order->payment_channel,
                'payment_url' => $order->payment_url,
                'paid_at' => $order->paid_at,
                'total_amount' => $order->total_amount,
            ]
        ]);
    }

    /**
     * Legacy: Simulate payment webhook (for testing without Xendit)
     * Route: POST /api/payments/webhook
     */
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
