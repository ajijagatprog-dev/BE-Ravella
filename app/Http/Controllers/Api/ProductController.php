<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['media', 'variants.media']);

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

        // Sale filter — products with active promotions (new system) or legacy discount/sale_price
        if ($request->boolean('on_sale')) {
            $query->where(function ($q) {
                $q->where('discount', '>', 0)
                  ->orWhere(function ($q2) {
                      $q2->whereNotNull('sale_price')->where('sale_price', '>', 0);
                  })
                  ->orWhereHas('promotions', function ($q3) {
                      $q3->where('is_active', true)
                         ->where(function ($q4) {
                             $q4->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                         })
                         ->where(function ($q4) {
                             $q4->whereNull('ends_at')->orWhere('ends_at', '>=', now());
                         });
                  });
            });
        }

        if ($request->boolean('is_flash_sale')) {
            $query->whereHas('promotions', function ($q) {
                $q->where('type', 'flash_sale')
                  ->where('is_active', true)
                  ->where(function ($q2) {
                      $q2->whereNull('starts_at')->orWhere('starts_at', '<=', now());
                  })
                  ->where(function ($q2) {
                      $q2->whereNull('ends_at')->orWhere('ends_at', '>=', now());
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
        // Accept either ID or slug — eager load media and variants
        $product = Product::with(['media', 'variants.media'])->where('id', $id)->orWhere('slug', $id)->first();

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
            'sku' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'discount' => 'nullable|integer|min:0|max:100',
            'rating' => 'nullable|numeric|min:0',
            'reviews' => 'nullable|integer|min:0',
            'features' => 'nullable|string',
            'specifications' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            // Multi-media uploads (max 8 main media: images up to 10MB, videos up to 50MB)
            'media_files' => 'nullable|array|max:8',
            'media_files.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,webm,mov|max:51200',
            // Variants JSON
            'variants_json' => 'nullable|string',
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

        // Remove non-product fields from data
        unset($data['media_files'], $data['variants_json']);

        // Handle JSON strings from FormData
        if (isset($data['features']) && is_string($data['features'])) {
            $data['features'] = json_decode($data['features'], true);
        }
        if (isset($data['specifications']) && is_string($data['specifications'])) {
            $data['specifications'] = json_decode($data['specifications'], true);
        }

        $product = Product::create($data);

        // Handle multi-media file uploads (main product images, max 8)
        if ($request->hasFile('media_files')) {
            $sortOrder = 0;
            foreach ($request->file('media_files') as $file) {
                $isVideo = in_array($file->getClientOriginalExtension(), ['mp4', 'webm', 'mov']);
                $folder = $isVideo ? 'products/videos' : 'products/images';
                $path = $file->store($folder, 'public');

                ProductMedia::create([
                    'product_id' => $product->id,
                    'variant_id' => null, // main product media
                    'type' => $isVideo ? 'video' : 'image',
                    'url' => Storage::url($path),
                    'sort_order' => $sortOrder,
                    'is_primary' => ($sortOrder === 0 && !$isVideo),
                ]);
                $sortOrder++;
            }

            // Set the product's legacy image field to the primary media
            $primaryMedia = $product->media()->where('is_primary', true)->first();
            if ($primaryMedia) {
                $product->update(['image' => $primaryMedia->getRawOriginal('url')]);
            }
        }

        // Handle variants
        if ($request->has('variants_json') && $request->variants_json) {
            $variants = json_decode($request->variants_json, true);
            if (is_array($variants)) {
                foreach ($variants as $idx => $variantData) {
                    $variant = ProductVariant::create([
                        'product_id' => $product->id,
                        'variant_type' => $variantData['variant_type'] ?? 'Warna',
                        'variant_value' => $variantData['variant_value'] ?? '',
                        'price' => isset($variantData['price']) && $variantData['price'] !== '' ? (int) $variantData['price'] : null,
                        'stock' => (int) ($variantData['stock'] ?? 0),
                        'sku_suffix' => $variantData['sku_suffix'] ?? null,
                        'sort_order' => $idx,
                        'is_default' => !empty($variantData['is_default']),
                    ]);

                    // Handle variant media files
                    $variantMediaKey = "variant_media_{$idx}";
                    if ($request->hasFile($variantMediaKey)) {
                        $vSortOrder = 0;
                        foreach ($request->file($variantMediaKey) as $file) {
                            $path = $file->store('products/variants', 'public');
                            ProductMedia::create([
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'type' => 'image',
                                'url' => Storage::url($path),
                                'sort_order' => $vSortOrder,
                                'is_primary' => ($vSortOrder === 0),
                            ]);
                            $vSortOrder++;
                        }
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $product->load(['media', 'variants.media'])
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
            'sku' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:255',
            'discount' => 'nullable|integer|min:0|max:100',
            'rating' => 'nullable|numeric|min:0',
            'reviews' => 'nullable|integer|min:0',
            'features' => 'nullable|string',
            'specifications' => 'nullable|string',
            'video_url' => 'nullable|string|max:500',
            // Multi-media uploads (max 8 main media)
            'media_files' => 'nullable|array|max:8',
            'media_files.*' => 'file|mimes:jpeg,png,jpg,gif,webp,mp4,webm,mov|max:51200',
            'delete_media_ids' => 'nullable|string',
            'primary_media_id' => 'nullable|integer',
            // Variants
            'variants_json' => 'nullable|string',
            'delete_variant_ids' => 'nullable|string',
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

        // Remove non-product fields from data
        unset($data['media_files'], $data['delete_media_ids'], $data['primary_media_id'], $data['variants_json'], $data['delete_variant_ids']);

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

        // Handle new media file uploads (main product media)
        if ($request->hasFile('media_files')) {
            $maxSort = ProductMedia::where('product_id', $product->id)->whereNull('variant_id')->max('sort_order') ?? -1;
            $sortOrder = $maxSort + 1;
            foreach ($request->file('media_files') as $file) {
                $isVideo = in_array($file->getClientOriginalExtension(), ['mp4', 'webm', 'mov']);
                $folder = $isVideo ? 'products/videos' : 'products/images';
                $path = $file->store($folder, 'public');

                ProductMedia::create([
                    'product_id' => $product->id,
                    'variant_id' => null,
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

            $primary = ProductMedia::find($request->primary_media_id);
            if ($primary) {
                $product->update(['image' => $primary->getRawOriginal('url')]);
            }
        }

        // Handle variant deletions
        if ($request->has('delete_variant_ids') && $request->delete_variant_ids) {
            $deleteVariantIds = json_decode($request->delete_variant_ids, true);
            if (is_array($deleteVariantIds)) {
                // Delete variant media files from storage first
                $variantMedia = ProductMedia::where('product_id', $product->id)
                    ->whereIn('variant_id', $deleteVariantIds)->get();
                foreach ($variantMedia as $media) {
                    $rawUrl = $media->getRawOriginal('url');
                    if ($rawUrl && Str::startsWith($rawUrl, '/storage/')) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $rawUrl));
                    }
                }
                // Cascade delete will handle media rows via FK
                ProductVariant::where('product_id', $product->id)
                    ->whereIn('id', $deleteVariantIds)->delete();
            }
        }

        // Handle variants create/update
        if ($request->has('variants_json') && $request->variants_json) {
            $variants = json_decode($request->variants_json, true);
            if (is_array($variants)) {
                foreach ($variants as $idx => $variantData) {
                    $variantFields = [
                        'product_id' => $product->id,
                        'variant_type' => $variantData['variant_type'] ?? 'Warna',
                        'variant_value' => $variantData['variant_value'] ?? '',
                        'price' => isset($variantData['price']) && $variantData['price'] !== '' && $variantData['price'] !== null ? (int) $variantData['price'] : null,
                        'stock' => (int) ($variantData['stock'] ?? 0),
                        'sku_suffix' => $variantData['sku_suffix'] ?? null,
                        'sort_order' => $idx,
                        'is_default' => !empty($variantData['is_default']),
                    ];

                    if (!empty($variantData['id'])) {
                        // Update existing variant
                        $variant = ProductVariant::where('id', $variantData['id'])
                            ->where('product_id', $product->id)->first();
                        if ($variant) {
                            $variant->update($variantFields);
                        }
                    } else {
                        // Create new variant
                        $variant = ProductVariant::create($variantFields);
                    }

                    // Handle variant media files (new uploads)
                    $variantMediaKey = "variant_media_{$idx}";
                    if ($request->hasFile($variantMediaKey) && $variant) {
                        $vMaxSort = ProductMedia::where('variant_id', $variant->id)->max('sort_order') ?? -1;
                        $vSortOrder = $vMaxSort + 1;
                        foreach ($request->file($variantMediaKey) as $file) {
                            $path = $file->store('products/variants', 'public');
                            ProductMedia::create([
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'type' => 'image',
                                'url' => Storage::url($path),
                                'sort_order' => $vSortOrder,
                                'is_primary' => ($vSortOrder === 0),
                            ]);
                            $vSortOrder++;
                        }
                    }

                    // Handle variant media deletions
                    $deleteVarMediaKey = "delete_variant_media_ids_{$idx}";
                    if ($request->has($deleteVarMediaKey) && $request->$deleteVarMediaKey) {
                        $deleteVarMediaIds = json_decode($request->$deleteVarMediaKey, true);
                        if (is_array($deleteVarMediaIds) && $variant) {
                            $varMediaToDelete = ProductMedia::where('variant_id', $variant->id)
                                ->whereIn('id', $deleteVarMediaIds)->get();
                            foreach ($varMediaToDelete as $media) {
                                $rawUrl = $media->getRawOriginal('url');
                                if ($rawUrl && Str::startsWith($rawUrl, '/storage/')) {
                                    Storage::disk('public')->delete(str_replace('/storage/', '', $rawUrl));
                                }
                                $media->delete();
                            }
                        }
                    }
                }
            }
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => $product->load(['media', 'variants.media'])
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

        // Delete all media files from storage (including variant media)
        foreach ($product->media as $media) {
            $rawUrl = $media->getRawOriginal('url');
            if ($rawUrl && Str::startsWith($rawUrl, '/storage/')) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $rawUrl));
            }
        }

        // Cascade delete will remove product_media and product_variants rows
        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully'
        ]);
    }
}
