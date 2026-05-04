<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    use HasFactory;

    protected $fillable = [
        'page',
        'slot',
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'slot' => 'integer',
    ];

    /**
     * Get full URL for the image attribute.
     */
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
