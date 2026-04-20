<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
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

    protected $appends = [
        'active_promotion',
        'promoted_price',
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function promotions()
    {
        return $this->hasMany(ProductPromotion::class, 'sku', 'sku');
    }

    /**
     * Get the currently active promotion for this product.
     * Prioritizes 'flash_sale' over 'discount'.
     */
    public function getActivePromotionAttribute()
    {
        if (!$this->sku) return null;

        // Try to find an active flash sale first
        $flashSale = ProductPromotion::active()
            ->where('sku', $this->sku)
            ->where('type', 'flash_sale')
            ->first();

        if ($flashSale) return $flashSale;

        // Fallback to active discount
        return ProductPromotion::active()
            ->where('sku', $this->sku)
            ->where('type', 'discount')
            ->first();
    }

    /**
     * Get the final price after applying active promotions.
     */
    public function getPromotedPriceAttribute()
    {
        $promo = $this->active_promotion;
        if (!$promo) {
            return $this->price;
        }

        return $promo->calculateDiscountedPrice($this->price);
    }
}
