<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BannerController extends Controller
{
    /**
     * List all banners grouped by page (admin).
     */
    public function index()
    {
        $banners = Banner::orderBy('page')->orderBy('slot')->get();

        // Group by page
        $grouped = $banners->groupBy('page')->map(function ($items) {
            return $items->values();
        });

        return response()->json([
            'status' => 'success',
            'data' => $grouped,
        ]);
    }

    /**
     * Get active banners for a specific page (public).
     */
    public function getPublicBanners(string $page)
    {
        $banners = Banner::where('page', $page)
            ->where('is_active', true)
            ->orderBy('slot')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $banners,
        ]);
    }

    /**
     * Update a banner image (admin).
     */
    public function update(Request $request, $id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Delete old image if exists
        if ($banner->getRawOriginal('image')) {
            $oldPath = $banner->getRawOriginal('image');
            $oldPath = str_replace('/storage/', '', $oldPath);
            $oldPath = str_replace('storage/', '', $oldPath);
            Storage::disk('public')->delete($oldPath);
        }

        // Store new image
        $imagePath = $request->file('image')->store('banners', 'public');
        $banner->image = Storage::url($imagePath);

        if ($request->has('is_active')) {
            $banner->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
        }

        $banner->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner updated successfully',
            'data' => $banner,
        ]);
    }

    /**
     * Toggle banner active status (admin).
     */
    public function toggleActive($id)
    {
        $banner = Banner::find($id);

        if (!$banner) {
            return response()->json([
                'status' => 'error',
                'message' => 'Banner not found',
            ], 404);
        }

        $banner->is_active = !$banner->is_active;
        $banner->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Banner status toggled',
            'data' => $banner,
        ]);
    }
}
