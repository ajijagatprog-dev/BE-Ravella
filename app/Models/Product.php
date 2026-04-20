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
        'calculated_rating',
        'total_reviews_count',
        'rating_distribution',
    ];

    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function approvedReviews()
    {
        return $this->hasMany(ProductReview::class)->where('status', 'approved');
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

    /**
     * Logic: If real reviews exist, calculate average.
     * Otherwise, fallback to the manual 'rating' field (admin input).
     */
    public function getCalculatedRatingAttribute()
    {
        $realReviews = $this->approvedReviews;
        
        if ($realReviews->count() > 0) {
            return round($realReviews->avg('rating'), 1);
        }

        // Fallback to Choice A: Manual admin input
        return (float) ($this->attributes['rating'] ?? 0);
    }

    /**
     * Logic: If real reviews exist, return count.
     * Otherwise, fallback to the manual 'reviews' field (admin input).
     */
    public function getTotalReviewsCountAttribute()
    {
        $realCount = $this->approvedReviews()->count();
        
        if ($realCount > 0) {
            return $realCount;
        }

        // Fallback to manual admin input
        return (int) ($this->attributes['reviews'] ?? 0);
    }

    /**
     * Calculate how many users gave 1, 2, 3, 4, 5 stars.
     */
    public function getRatingDistributionAttribute()
    {
        $realReviews = $this->approvedReviews;
        
        $distribution = [
            5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0
        ];

        if ($realReviews->count() > 0) {
            foreach ($realReviews as $review) {
                $distribution[$review->rating]++;
            }
        } else {
            // Fake distribution based on manual rating if no real data
            $manualRating = round($this->attributes['rating'] ?? 0);
            if ($manualRating > 0) {
                $distribution[$manualRating] = $this->attributes['reviews'] ?? 0;
            }
        }

        return $distribution;
    }

    /**
     * Mode: The most frequent rating given by users.
     */
    public function getMostFrequentRatingAttribute()
    {
        $dist = $this->rating_distribution;
        arsort($dist);
        return key($dist);
    }
}
