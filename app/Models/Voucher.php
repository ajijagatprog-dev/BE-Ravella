<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_purchase',
        'max_discount',
        'max_uses',
        'used_count',
        'is_active',
        'expires_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'value' => 'decimal:2',
        'min_purchase' => 'decimal:2',
        'max_discount' => 'decimal:2',
    ];

    /**
     * Check if voucher is currently valid (active, not expired, not maxed out).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) return false;
        return true;
    }

    /**
     * Calculate discount amount for a given subtotal.
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->type === 'percent') {
            $discount = ($subtotal * $this->value) / 100;
            if ($this->max_discount !== null) {
                $discount = min($discount, (float) $this->max_discount);
            }
            return round($discount);
        }

        // fixed
        return min((float) $this->value, $subtotal);
    }
}
