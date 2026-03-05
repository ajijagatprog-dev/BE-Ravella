<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;

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
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric',
        ]);

        // Get shipping address details to snapshot
        $address = $user->addresses()->where('id', $validated['shipping_address_id'])->firstOrFail();

        $totalAmount = 0;
        foreach ($validated['items'] as $item) {
            $totalAmount += $item['price'] * $item['quantity'];
        }

        // Add shipping cost (Free if > 500.000)
        $shippingFee = $totalAmount > 500000 ? 0 : 25000;
        $totalAmount += $shippingFee;

        $order = $user->orders()->create([
            'order_number' => 'RH-' . strtoupper(uniqid()),
            'total_amount' => $totalAmount,
            'status' => 'PENDING',
            'payment_method' => $validated['payment_method'],
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

        return response()->json([
            'status' => 'success',
            'message' => 'Order created successfully',
            'data' => $order
        ], 201);
    }
}
