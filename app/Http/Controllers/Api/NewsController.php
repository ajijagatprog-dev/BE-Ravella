<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class NewsController extends Controller
{
    public function index(Request $request)
    {
        $limit = $request->query('limit', 10);
        $status = $request->query('status');

        $query = News::query();

        if ($status) {
            $query->where('status', $status);
        }

        $news = $query->latest()->paginate($limit);

        return response()->json([
            'status' => 'success',
            'data' => $news
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'excerpt' => 'nullable|string',
            'author' => 'nullable|string|max:255',
            'read_time' => 'nullable|string|max:255',
            'views' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'status' => 'nullable|in:draft,published',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Validation failed creating news: ' . json_encode($validator->errors()));
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        $data['slug'] = Str::slug($data['title']) . '-' . uniqid();
        
        if (empty($data['category'])) {
            $data['category'] = 'General';
        }
        if (empty($data['status'])) {
            $data['status'] = 'published';
        }
        
        if ($data['status'] === 'published') {
            $data['published_at'] = now();
        }

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('news', 'public');
            $data['image'] = Storage::url($imagePath);
        }

        if (array_key_exists('is_featured', $data)) {
            $data['is_featured'] = filter_var($data['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }

        $news = News::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'News article created successfully',
            'data' => $news
        ], 201);
    }

    public function show(string $id)
    {
        $news = News::where('slug', $id)->orWhere('id', $id)->first();

        if (!$news) {
            return response()->json([
                'status' => 'error',
                'message' => 'News article not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $news
        ]);
    }

    public function update(Request $request, string $id)
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json([
                'status' => 'error',
                'message' => 'News article not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'content' => 'string',
            'excerpt' => 'nullable|string',
            'author' => 'nullable|string|max:255',
            'read_time' => 'nullable|string|max:255',
            'views' => 'nullable|integer|min:0',
            'is_featured' => 'nullable|boolean',
            'category' => 'nullable|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'status' => 'nullable|in:draft,published',
        ]);

        if ($validator->fails()) {
            \Illuminate\Support\Facades\Log::error('Validation failed updating news: ' . json_encode($validator->errors()));
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        if (isset($data['title']) && $data['title'] !== $news->title) {
            $data['slug'] = Str::slug($data['title']) . '-' . uniqid();
        }

        if (isset($data['status']) && $data['status'] !== $news->status) {
            if ($data['status'] === 'published') {
                $data['published_at'] = now();
            } else {
                $data['published_at'] = null;
            }
        }

        if ($request->hasFile('image')) {
            if ($news->image) {
                // Remove base URL if present to delete properly
                $oldPath = str_replace(asset('/'), '', $news->image);
                $oldPath = str_replace(env('APP_URL') . '/', '', $oldPath);
                $oldPath = str_replace('storage/', '', $oldPath);
                $oldPath = str_replace('/storage/', '', $oldPath);
                Storage::disk('public')->delete($oldPath);
            }
            $imagePath = $request->file('image')->store('news', 'public');
            $data['image'] = Storage::url($imagePath);
        } elseif (array_key_exists('image', $data)) {
            unset($data['image']);
        }

        if (array_key_exists('is_featured', $data)) {
            $data['is_featured'] = filter_var($data['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }

        $news->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'News article updated successfully',
            'data' => $news
        ]);
    }

    public function destroy(string $id)
    {
        $news = News::find($id);

        if (!$news) {
            return response()->json([
                'status' => 'error',
                'message' => 'News article not found'
            ], 404);
        }

        if ($news->image) {
            $oldPath = str_replace(asset('/'), '', $news->image);
            $oldPath = str_replace(env('APP_URL') . '/', '', $oldPath);
            $oldPath = str_replace('storage/', '', $oldPath);
            $oldPath = str_replace('/storage/', '', $oldPath);
            Storage::disk('public')->delete($oldPath);
        }

        $news->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'News article deleted successfully'
        ]);
    }
}
