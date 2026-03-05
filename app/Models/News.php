<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class News extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'content', 'excerpt', 'image', 'category', 'author', 'status', 'published_at',
        'read_time', 'views', 'is_featured'
    ];

    public function getImageAttribute($value)
    {
        if ($value) {
            // Check if it already has a full URL
            if (str_starts_with($value, 'http')) {
                return $value;
            }
            // Add the app's base URL
            return asset($value);
        }
        return null;
    }
}
