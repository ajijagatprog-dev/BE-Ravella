<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductPromotion;
use App\Imports\ProductPromotionImport;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Log;

class PromotionController extends Controller
{
    /**
     * List all promotions.
     */
    public function index(Request $request)
    {
        $type = $request->query('type', 'discount');
        $promotions = ProductPromotion::where('type', $type)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $promotions
        ]);
    }

    /**
     * Store a new promotion manually.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'sku' => 'required|string',
            'name' => 'nullable|string',
            'type' => 'required|in:discount,flash_sale',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);

        // Validate SKU existence
        if (!\App\Models\Product::where('sku', $validated['sku'])->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Produk dengan SKU ini tidak ditemukan. Pastikan produk memiliki kode SKU di data produk.'
            ], 422);
        }

        // Prevent duplicate promotion types for the same SKU
        ProductPromotion::where('sku', $validated['sku'])
            ->where('type', $validated['type'])
            ->delete();

        $data = $validated;
        
        if (!empty($data['starts_at'])) {
            $data['starts_at'] = \Carbon\Carbon::parse($data['starts_at'])->startOfDay();
        }
        
        if (!empty($data['ends_at'])) {
            $data['ends_at'] = \Carbon\Carbon::parse($data['ends_at'])->endOfDay();
        }

        $promotion = ProductPromotion::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Promotion created successfully',
            'data' => $promotion
        ]);
    }

    /**
     * Update an existing promotion.
     */
    public function update(Request $request, $id)
    {
        $promotion = ProductPromotion::findOrFail($id);
        
        $validated = $request->validate([
            'sku' => 'string',
            'name' => 'nullable|string',
            'discount_type' => 'in:percent,fixed',
            'discount_value' => 'numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'boolean',
        ]);

        if (isset($validated['sku']) && $validated['sku'] !== $promotion->sku) {
            // Validate SKU existence
            if (!\App\Models\Product::where('sku', $validated['sku'])->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Produk dengan SKU ini tidak ditemukan.'
                ], 422);
            }
        }

        $data = $validated;

        if (!empty($data['starts_at'])) {
            $data['starts_at'] = \Carbon\Carbon::parse($data['starts_at'])->startOfDay();
        }
        
        if (!empty($data['ends_at'])) {
            $data['ends_at'] = \Carbon\Carbon::parse($data['ends_at'])->endOfDay();
        }

        $promotion->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Promotion updated successfully',
            'data' => $promotion
        ]);
    }

    /**
     * Delete a promotion.
     */
    public function destroy($id)
    {
        $promotion = ProductPromotion::findOrFail($id);
        $promotion->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Promotion deleted successfully'
        ]);
    }

    /**
     * Bulk Upload via Excel.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv',
            'type' => 'required|in:discount,flash_sale'
        ]);

        try {
            Excel::import(new ProductPromotionImport($request->type), $request->file('file'));

            return response()->json([
                'status' => 'success',
                'message' => 'Promotions imported successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Promotion Import Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import promotions: ' . $e->getMessage()
            ], 500);
        }
    }
}
