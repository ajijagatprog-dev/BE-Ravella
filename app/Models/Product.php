<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'sale_price',
        'stock',
        'weight',
        'category',
        'image',
        'is_featured',
        'badge',
        'discount',
        'rating',
        'reviews',
        'features',
        'specifications',
    ];

    protected $casts = [
        'features' => 'array',
        'specifications' => 'array',
    ];

    public function getImageAttribute($value)
    {
        if ($value) {
            if (str_starts_with($value, 'http')) {
                return $value;
            }
            return asset($value);
        }
        return null;
    }
}
