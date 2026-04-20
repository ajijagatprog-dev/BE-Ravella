<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Voucher;
use Carbon\Carbon;
use App\Services\TrackingService;
use Illuminate\Support\Facades\Log;
use App\Mail\NewOrderAdminMail;
use App\Mail\OrderSuccessCustomerMail;
use App\Services\FonnteService;
use Illuminate\Support\Facades\Mail;

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
            'courier' => 'nullable|string',
            'shipping_cost' => 'nullable|numeric',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
            'voucher_code' => 'nullable|string',
            'source' => 'nullable|string',
            'utm_source' => 'nullable|string',
            'utm_medium' => 'nullable|string',
            'utm_campaign' => 'nullable|string',
        ]);

        // Get shipping address details to snapshot
        $address = $user->addresses()->where('id', $validated['shipping_address_id'])->firstOrFail();

        $subtotal = 0;
        foreach ($validated['items'] as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }

        // Apply voucher discount if provided
        $discountAmount = 0;
        $appliedVoucherCode = null;
        if (!empty($validated['voucher_code'])) {
            $voucher = Voucher::where('code', strtoupper(trim($validated['voucher_code'])))->first();
            if ($voucher && $voucher->isValid() && $subtotal >= (float) $voucher->min_purchase) {
                $discountAmount = $voucher->calculateDiscount($subtotal);
                $appliedVoucherCode = $voucher->code;
                $voucher->increment('used_count');
            }
        }

        // Add shipping cost (Use provided RajaOngkir cost or fallback)
        $shippingFee = $validated['shipping_cost'] ?? ($subtotal > 500000 ? 0 : 25000);
        $totalAmount = $subtotal - $discountAmount + $shippingFee;
        $totalAmount = max(0, $totalAmount);

        $order = $user->orders()->create([
            'order_number' => 'RH-' . strtoupper(uniqid()),
            'total_amount' => $totalAmount,
            'status' => 'PENDING',
            'payment_method' => $validated['payment_method'],
            'voucher_code' => $appliedVoucherCode,
            'discount_amount' => $discountAmount,
            'shipping_cost' => $shippingFee,
            'utm_source' => $validated['utm_source'] ?? null,
            'utm_medium' => $validated['utm_medium'] ?? null,
            'utm_campaign' => $validated['utm_campaign'] ?? null,
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

        // --- Create Xendit Invoice ---
        try {
            $apiInstance = new \Xendit\Invoice\InvoiceApi();
            $apiInstance->setApiKey(env('XENDIT_API_KEY'));

            // Build item details for Xendit
            $xenditItems = [];
            foreach ($order->items()->with('product')->get() as $orderItem) {
                $xenditItems[] = new \Xendit\Invoice\InvoiceItem([
                    'name' => $orderItem->product->name ?? 'Product',
                    'quantity' => $orderItem->quantity,
                    'price' => (float) $orderItem->price,
                ]);
            }

            // ADD Shipping Fee to Xendit
            if ($shippingFee > 0) {
                $xenditItems[] = new \Xendit\Invoice\InvoiceItem([
                    'name' => 'Shipping Fee (' . ($validated['courier'] ?? 'Standard') . ')',
                    'quantity' => 1,
                    'price' => (float) $shippingFee,
                ]);
            }

            // ADD Discount to Xendit
            if ($discountAmount > 0) {
                $xenditItems[] = new \Xendit\Invoice\InvoiceItem([
                    'name' => 'Discount (' . ($appliedVoucherCode ?? 'Voucher') . ')',
                    'quantity' => 1,
                    'price' => (float) -$discountAmount,
                ]);
            }

            $source = $request->input('source', $user->role === 'b2b' ? 'b2b' : 'retail');
            $redirectQuery = '&source=' . $source;

            $createInvoiceRequest = new \Xendit\Invoice\CreateInvoiceRequest([
                'external_id' => $order->order_number,
                'amount' => (float) $order->total_amount,
                'payer_email' => $user->email,
                'description' => 'Pembayaran pesanan ' . $order->order_number . ' - Ravella',
                'invoice_duration' => 86400, // 24 hours
                'currency' => 'IDR',
                'items' => $xenditItems,
                'success_redirect_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/payment/success?order=' . $order->order_number . $redirectQuery,
                'failure_redirect_url' => env('FRONTEND_URL', 'http://localhost:3000') . '/payment/failed?order=' . $order->order_number . $redirectQuery,
                'metadata' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'source' => $source
                ],
            ]);

            $invoice = $apiInstance->createInvoice($createInvoiceRequest);

            // Save Xendit data to order
            $order->update([
                'xendit_invoice_id' => $invoice->getId(),
                'payment_url' => $invoice->getInvoiceUrl(),
            ]);

            Log::info("Xendit Invoice created for order: {$order->order_number}, Invoice ID: {$invoice->getId()}");

            // --- SEND NOTIFICATIONS (EMAIL & WHATSAPP) ---
            try {
                // 1. WhatsApp Configuration
                $fonnte = new FonnteService();
                $customerPhone = $address->phone_number; 
                $adminPhone = config('fonnte.admin_phone');

                // 2. Format Currency
                $formattedTotal = 'Rp ' . number_format($order->total_amount, 0, ',', '.');
                $paymentUrl = $invoice->getInvoiceUrl();

                // 3. Messages Setup
                $waCustomerMsg = "Halo {$user->name},\n\nTerima kasih telah berbelanja di Ravella!\n\nPesanan Anda dengan nomor *{$order->order_number}* berhasil dibuat.\nTotal Tagihan: *{$formattedTotal}*\n\nSilakan selesaikan pembayaran langsung melalui sistem kami pada link berikut (jika belum):\n{$paymentUrl}\n\nAbaikan pesan ini jika Anda sudah mambayar. Terima kasih.\n\n_Pesan ini dikirim secara otomatis._";
                
                $waAdminMsg = "🚨 *PESANAN BARU MASUK* 🚨\n\nNo. Order: {$order->order_number}\nPelanggan: {$user->name}\nTotal: {$formattedTotal}\nStatus: PENDING (Menunggu Pembayaran)\n\nSilakan pantau melalui Dashboard Admin.";

                // 4. Send WhatsApp Executions
                if ($customerPhone) $fonnte->sendMessage($customerPhone, $waCustomerMsg);
                if ($adminPhone) $fonnte->sendMessage($adminPhone, $waAdminMsg);

                // 5. Send Emails
                Mail::to($user->email)->send(new OrderSuccessCustomerMail($order));
                
                $adminEmail = config('mail.admin_email');
                if ($adminEmail) {
                    Mail::to($adminEmail)->send(new NewOrderAdminMail($order));
                }
                
                Log::info("All notifications (WA & Email) queued successfully for order: {$order->order_number}");
            } catch (\Exception $e) {
                // Prevent order failure if notifications error out (e.g., SMTP timeout)
                Log::error("Failed to send order notifications: " . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => $order->fresh(),
                'payment_url' => $invoice->getInvoiceUrl(),
            ], 201);

        } catch (\Xendit\XenditSdkException $e) {
            Log::error("Xendit Invoice creation failed for order: {$order->order_number}. Error: " . $e->getMessage());

            // Order was created but payment failed — return order with error info
            return response()->json([
                'status' => 'success',
                'message' => 'Order created but payment gateway error. Please retry payment.',
                'data' => $order,
                'payment_url' => null,
                'payment_error' => $e->getMessage(),
            ], 201);
        } catch (\Exception $e) {
            Log::error("Unexpected error creating Xendit Invoice: " . $e->getMessage());

            return response()->json([
                'status' => 'success',
                'message' => 'Order created but payment gateway unavailable.',
                'data' => $order,
                'payment_url' => null,
                'payment_error' => $e->getMessage(),
            ], 201);
        }
    }

    // --- ADMIN METHODS ---

    // GET: /api/admin/orders
    public function getAllOrders(Request $request)
    {
        // Admin user validation should ideally be middleware, assuming sanctum user is admin
        $orders = Order::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->get();

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
            'status' => 'required|string|in:PENDING,PAID,PROCESSING,SHIPPED,DELIVERED,CANCELLED',
            'courier' => 'nullable|string',
            'tracking_number' => 'nullable|string',
        ]);

        $order = Order::where('order_number', $order_number)->firstOrFail();
        $previousStatus = $order->status;

        // Auto-update status to SHIPPED if tracking number is newly provided and state is processing or pending
        $newStatus = $validated['status'];
        if (!empty($validated['tracking_number']) && in_array($previousStatus, ['PENDING', 'PROCESSING'])) {
             // Automasi: jika diinput resi, otomatis status berubah jadi SHIPPED
             if (!empty($validated['courier'])) {
                 $newStatus = 'SHIPPED';
             }
        }

        $order->update([
            'status' => $newStatus,
            'courier' => $validated['courier'] ?? $order->courier,
            'tracking_number' => $validated['tracking_number'] ?? $order->tracking_number,
        ]);

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

    // GET: /api/customer/orders/{order_number}/tracking
    public function trackOrder(Request $request, $order_number, TrackingService $trackingService)
    {
        $order = Order::where('order_number', $order_number)->firstOrFail();

        // Ensure user owns the order, unless admin
        if ($request->user() && $request->user()->id !== $order->user_id && $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if (!$order->tracking_number || !$order->courier) {
            return response()->json([
                'status' => 'error',
                'message' => 'Resi belum tersedia untuk pesanan ini.'
            ], 404);
        }

        $trackingData = $trackingService->getTrackingInfo($order->tracking_number, $order->courier);

        return response()->json([
            'status' => $trackingData['success'] ? 'success' : 'error',
            'message' => $trackingData['message'] ?? 'Berhasil melacak resi',
            'data' => $trackingData['data'] ?? null
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

        // --- Pesanan Aktif (Active orders: PROCESSING, SHIPPED) ---
        $activeOrders = Order::whereIn('status', ['PROCESSING', 'SHIPPED'])->count();

        $activeToday = Order::whereIn('status', ['PROCESSING', 'SHIPPED'])
            ->where('created_at', '>=', $today)
            ->count();

        $activeYesterday = Order::whereIn('status', ['PROCESSING', 'SHIPPED'])
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
