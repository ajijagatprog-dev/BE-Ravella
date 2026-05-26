<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoyaltyClaim extends Model
{
    protected $fillable = [
        'user_id',
        'reward_id',
        'tier_name',
        'reward_type',
        'reward_value',
        'claimed_at',
    ];

    protected $casts = [
        'claimed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
