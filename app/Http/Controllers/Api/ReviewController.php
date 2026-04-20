<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Submit a review (Customer)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:products,id',
            'order_id' => 'required|exists:orders,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'images' => 'nullable|array',
            'images.*' => 'string', // URLs or base64
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Verify that the order belongs to the user and is DELIVERED
        $order = Order::where('id', $request->order_id)
            ->where('user_id', auth()->id())
            ->first();

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order tidak ditemukan.'
            ], 404);
        }

        if ($order->status !== 'DELIVERED') {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda hanya bisa memberikan ulasan untuk pesanan yang sudah diterima (DELIVERED).'
            ], 403);
        }

        // Check if user already reviewed this product for this order
        $existing = ProductReview::where('order_id', $request->order_id)
            ->where('product_id', $request->product_id)
            ->where('user_id', auth()->id())
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda sudah memberikan ulasan untuk produk ini di pesanan ini.'
            ], 422);
        }

        $review = ProductReview::create([
            'product_id' => $request->product_id,
            'user_id' => auth()->id(),
            'order_id' => $request->order_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'images' => $request->images,
            'status' => 'pending' // Moderation required
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Ulasan berhasil dikirim dan sedang menunggu moderasi.',
            'data' => $review
        ]);
    }

    /**
     * Get approved reviews for a product (Public)
     */
    public function getProductReviews($productId)
    {
        $reviews = ProductReview::with(['user:id,name'])
            ->where('product_id', $productId)
            ->approved()
            ->latest()
            ->paginate(10);

        return response()->json([
            'status' => 'success',
            'data' => $reviews
        ]);
    }

    /**
     * Get reviews for admin management (Admin)
     */
    public function index(Request $request)
    {
        $status = $request->query('status');
        
        $query = ProductReview::with(['user:id,name', 'product:id,name,image']);

        if ($status) {
            $query->where('status', $status);
        }

        $reviews = $query->latest()->paginate(20);

        return response()->json([
            'status' => 'success',
            'data' => $reviews
        ]);
    }

    /**
     * Update review status (Admin)
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'admin_reply' => 'nullable|string'
        ]);

        $review = ProductReview::findOrFail($id);
        $review->update([
            'status' => $request->status,
            'admin_reply' => $request->admin_reply
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status ulasan berhasil diperbarui.',
            'data' => $review
        ]);
    }

    /**
     * Add admin reply to a review
     */
    public function reply(Request $request, $id)
    {
        $request->validate([
            'admin_reply' => 'required|string|max:1000'
        ]);

        $review = ProductReview::findOrFail($id);
        $review->update([
            'admin_reply' => $request->admin_reply,
            'status' => 'approved' // Auto-approve if replied? Or keep original? Usually you reply to approved ones.
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Balasan berhasil dikirim.',
            'data' => $review
        ]);
    }

    /**
     * Delete review (Admin)
     */
    public function destroy($id)
    {
        $review = ProductReview::findOrFail($id);
        $review->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Ulasan berhasil dihapus.'
        ]);
    }
}
