<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Address extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'label',
        'is_primary',
        'recipient_name',
        'phone_number',
        'full_address',
        'city',
        'province',
        'postal_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
