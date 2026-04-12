<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with('media');

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%')
                  ->orWhere('category', 'like', '%' . $search . '%');
            });
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
        // Accept either ID or slug — eager load media
        $product = Product::with('media')->where('id', $id)->orWhere('slug', $id)->first();

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
        ini_set('max_execution_time', '120');

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
            'video_url' => 'nullable|string|max:500',
            // Multi-media uploads
            'media_files' => 'nullable|array|max:10',
            'media_files.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,webm,mov|max:51200',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed during product creation: ' . json_encode($validator->errors()));
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['slug'] = Str::slug($data['name']) . '-' . uniqid();

        // Handle legacy single image
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = Storage::url($imagePath);
        }

        // Remove media_files from data — handled separately
        unset($data['media_files']);

        // Handle JSON strings from FormData
        if (isset($data['features']) && is_string($data['features'])) {
            $data['features'] = json_decode($data['features'], true);
        }
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $data['specifications'] = json_decode($data['specifications'], true);
        }

        $product = Product::create($data);

        // Handle multi-media file uploads
        if ($request->hasFile('media_files')) {
            $sortOrder = 0;
            foreach ($request->file('media_files') as $file) {
                $isVideo = in_array($file->getClientOriginalExtension(), ['mp4', 'webm', 'mov']);
                $folder = $isVideo ? 'products/videos' : 'products/images';
                $path = $file->store($folder, 'public');

                ProductMedia::create([
                    'product_id' => $product->id,
                    'type' => $isVideo ? 'video' : 'image',
                    'url' => Storage::url($path),
                    'sort_order' => $sortOrder,
                    'is_primary' => ($sortOrder === 0 && !$isVideo), // First image is primary
                ]);
                $sortOrder++;
            }

            // Set the product's legacy image field to the primary media
            $primaryMedia = $product->media()->where('is_primary', true)->first();
            if ($primaryMedia) {
                $product->update(['image' => $primaryMedia->getRawOriginal('url')]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $product->load('media')
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
            'video_url' => 'nullable|string|max:500',
            // Multi-media uploads
            'media_files' => 'nullable|array|max:10',
            'media_files.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,webm,mov|max:51200',
            'delete_media_ids' => 'nullable|string', // JSON string of IDs to delete
            'primary_media_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed during product update: ' . json_encode($validator->errors()));
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
            if ($product->image && Str::startsWith($product->image, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $product->image);
                Storage::disk('public')->delete($oldPath);
            }
            $imagePath = $request->file('image')->store('products', 'public');
            $data['image'] = Storage::url($imagePath);
        } else {
            if (array_key_exists('image', $data)) {
                unset($data['image']);
            }
        }

        // Remove media-related fields from product data
        unset($data['media_files'], $data['delete_media_ids'], $data['primary_media_id']);

        // Handle JSON strings from FormData
        if (isset($data['features']) && is_string($data['features'])) {
            $data['features'] = json_decode($data['features'], true);
        }
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $data['specifications'] = json_decode($data['specifications'], true);
        }

        $product->update($data);

        // Handle media deletions
        if ($request->has('delete_media_ids') && $request->delete_media_ids) {
            $deleteIds = json_decode($request->delete_media_ids, true);
            if (is_array($deleteIds)) {
                $mediaToDelete = ProductMedia::where('product_id', $product->id)
                    ->whereIn('id', $deleteIds)->get();
                foreach ($mediaToDelete as $media) {
                    $rawUrl = $media->getRawOriginal('url');
                    if ($rawUrl && Str::startsWith($rawUrl, '/storage/')) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $rawUrl));
                    }
                    $media->delete();
                }
            }
        }

        // Handle new media file uploads
        if ($request->hasFile('media_files')) {
            $maxSort = ProductMedia::where('product_id', $product->id)->max('sort_order') ?? -1;
            $sortOrder = $maxSort + 1;
            foreach ($request->file('media_files') as $file) {
                $isVideo = in_array($file->getClientOriginalExtension(), ['mp4', 'webm', 'mov']);
                $folder = $isVideo ? 'products/videos' : 'products/images';
                $path = $file->store($folder, 'public');

                ProductMedia::create([
                    'product_id' => $product->id,
                    'type' => $isVideo ? 'video' : 'image',
                    'url' => Storage::url($path),
                    'sort_order' => $sortOrder,
                    'is_primary' => false,
                ]);
                $sortOrder++;
            }
        }

        // Handle primary media change
        if ($request->has('primary_media_id') && $request->primary_media_id) {
            ProductMedia::where('product_id', $product->id)->update(['is_primary' => false]);
            ProductMedia::where('product_id', $product->id)
                ->where('id', $request->primary_media_id)
                ->update(['is_primary' => true]);

            // Also update legacy image field
            $primary = ProductMedia::find($request->primary_media_id);
            if ($primary) {
                $product->update(['image' => $primary->getRawOriginal('url')]);
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => $product->load('media')
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

        // Delete legacy image
        if ($product->image && Str::startsWith($product->image, '/storage/')) {
            $oldPath = str_replace('/storage/', '', $product->image);
            Storage::disk('public')->delete($oldPath);
        }

        // Delete all media files from storage
        foreach ($product->media as $media) {
            $rawUrl = $media->getRawOriginal('url');
            if ($rawUrl && Str::startsWith($rawUrl, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $rawUrl));
            }
        }

        // Cascade delete will remove product_media rows
        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ]);
    }
}
