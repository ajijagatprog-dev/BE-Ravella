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
        'b2b_price',
        'b2b_min_order',
        'stock',
        'weight',
        'category',
        'image',
        'video_url',
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

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'product_id');
    }

    public function media()
    {
        return $this->hasMany(ProductMedia::class)->orderBy('sort_order');
    }

    /**
     * Main product media (not attached to any variant).
     */
    public function mainMedia()
    {
        return $this->hasMany(ProductMedia::class)->whereNull('variant_id')->orderBy('sort_order');
    }

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }
}
