<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductReview extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::saved(function ($review) {
            self::updateProductRatingAndReviews($review->product_id);
        });

        static::deleted(function ($review) {
            self::updateProductRatingAndReviews($review->product_id);
        });
    }

    public static function updateProductRatingAndReviews($productId)
    {
        $product = Product::find($productId);
        if ($product) {
            $reviewsQuery = self::where('product_id', $productId)->where('status', 'approved');
            $count = $reviewsQuery->count();
            $avgRating = $count > 0 ? round($reviewsQuery->avg('rating'), 1) : 0;

            $product->update([
                'reviews' => $count,
                'rating' => $avgRating
            ]);
        }
    }

    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
        'images',
        'status',
        'admin_reply',
        'edit_rating',
        'edit_comment',
        'edit_images',
        'edit_status',
    ];

    protected $casts = [
        'images' => 'array',
        'rating' => 'integer',
        'edit_images' => 'array',
        'edit_rating' => 'integer',
    ];

    protected $appends = [
        'is_editable',
    ];

    public function getIsEditableAttribute()
    {
        // Review can only be edited within 5 days of its creation
        return $this->created_at ? $this->created_at->addDays(5)->isFuture() : false;
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Scope for approved reviews only
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
