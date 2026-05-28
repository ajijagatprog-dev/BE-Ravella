<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'quantity',
        'price',
    ];

    protected $appends = ['has_review'];

    public function review()
    {
        return $this->hasOne(ProductReview::class, 'product_id', 'product_id')
            ->where('product_reviews.order_id', $this->order_id);
    }

    public function getHasReviewAttribute()
    {
        if ($this->relationLoaded('review')) {
            return !is_null($this->review);
        }

        return ProductReview::where('product_id', $this->product_id)
            ->where('order_id', $this->order_id)
            ->exists();
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
