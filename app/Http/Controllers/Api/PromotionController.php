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

        $promotion = ProductPromotion::create($validated);

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

        $promotion->update($validated);

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
