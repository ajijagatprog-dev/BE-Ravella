<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'sku',
        'name',
        'type',
        'discount_type',
        'discount_value',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'discount_value' => 'decimal:2',
    ];

    /**
     * Scope to only include active promotions.
     */
    public function scopeActive($query)
    {
        $now = now();
        return $query->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                  ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                  ->orWhere('ends_at', '>=', $now);
            });
    }

    /**
     * Check if the promotion is currently valid.
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        $now = now();
        if ($this->starts_at && $this->starts_at->isFuture()) return false;
        if ($this->ends_at && $this->ends_at->isPast()) return false;
        return true;
    }

    /**
     * Calculate the discount amount for a given price.
     */
    public function calculateDiscountedPrice(float $originalPrice): float
    {
        if ($this->discount_type === 'percent') {
            $discountAmount = ($originalPrice * $this->discount_value) / 100;
            return max(0, $originalPrice - $discountAmount);
        }

        // Fixed discount
        return max(0, $originalPrice - (float) $this->discount_value);
    }
}
