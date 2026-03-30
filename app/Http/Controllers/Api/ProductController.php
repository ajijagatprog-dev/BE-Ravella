<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sale filter — products with discount > 0 or sale_price set
        if ($request->boolean('on_sale')) {
            $query->where(function ($q) {
                $q->where('discount', '>', 0)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('sale_price')->where('sale_price', '>', 0);
                  });
            });
        }

        $products = $query->latest()->paginate($request->get('limit', 15));

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    public function show($id)
    {
        // Accept either ID or slug
        $product = Product::where('id', $id)->orWhere('slug', $id)->first();

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|integer|min:0',
            'sale_price' => 'nullable|integer|min:0',
            'b2b_price' => 'nullable|integer|min:0',
            'b2b_min_order' => 'nullable|integer|min:1',
            'stock' => 'required|integer|min:0',
            'weight' => 'required|integer|min:0',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_featured' => 'boolean',
            'badge' => 'nullable|string|max:255',
            'discount' => 'nullable|integer|min:0|max:100',
            'rating' => 'nullable|numeric|min:0',
            'reviews' => 'nullable|integer|min:0',
            'features' => 'nullable|string',
            'specifications' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Validation failed during product creation: ' . json_encode($validator->errors()));
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['slug'] = Str::slug($data['name']) . '-' . uniqid();

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = Storage::url($imagePath);
        }

        // Handle JSON strings from FormData if they were sent as strings instead of arrays
        if (isset($data['features']) && is_string($data['features'])) {
            $data['features'] = json_decode($data['features'], true);
        }
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $data['specifications'] = json_decode($data['specifications'], true);
        }

        $product = Product::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'string',
            'price' => 'integer|min:0',
            'sale_price' => 'nullable|integer|min:0',
            'b2b_price' => 'nullable|integer|min:0',
            'b2b_min_order' => 'nullable|integer|min:1',
            'stock' => 'integer|min:0',
            'weight' => 'integer|min:0',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'is_featured' => 'boolean',
            'badge' => 'nullable|string|max:255',
            'discount' => 'nullable|integer|min:0|max:100',
            'rating' => 'nullable|numeric|min:0',
            'reviews' => 'nullable|integer|min:0',
            'features' => 'nullable|string',
            'specifications' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Validation failed during product update: ' . json_encode($validator->errors()));
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['name']) && $data['name'] !== $product->name) {
            $data['slug'] = Str::slug($data['name']) . '-' . uniqid();
        }

        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($product->image && Str::startsWith($product->image, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $product->image);
                Storage::disk('public')->delete($oldPath);
            }
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = Storage::url($imagePath);
        } else {
            // Remove image from data if not uploaded so it doesn't try to update DB with empty value (unless explicitly set to null by something else, but file uploads won't be string)
            if (array_key_exists('image', $data)) {
                unset($data['image']);
            }
        }

        // Handle JSON strings from FormData if they were sent as strings instead of arrays
        if (isset($data['features']) && is_string($data['features'])) {
            $data['features'] = json_decode($data['features'], true);
        }
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $data['specifications'] = json_decode($data['specifications'], true);
        }

        $product->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => $product
        ]);
    }

    public function destroy($id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found'
            ], 404);
        }

        if ($product->image && Str::startsWith($product->image, '/storage/')) {
            $oldPath = str_replace('/storage/', '', $product->image);
            Storage::disk('public')->delete($oldPath);
        }

        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ]);
    }
}
